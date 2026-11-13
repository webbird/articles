<?php
/**
 * articles – Frontend
 * List and detail of a section: filters, paging, neighbours, placeholder values.
 * Tags, groups, users and gallery counts are loaded once per list (no N+1).
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Frontend
{
    private const TEXTS = [
        'TEXT_AT', 'TEXT_BACK', 'TEXT_LAST_CHANGED', 'TEXT_NEXT_POST', 'TEXT_O_CLOCK',
        'TEXT_ON', 'TEXT_POSTED_BY', 'TEXT_PREV_POST', 'TEXT_READ_MORE',
    ];

    /** @var array<string, true> tag titles of everything rendered, for the keywords meta tag */
    private array $keywords = [];

    /** @param array $settings settings row plus page_id, page_link, page_title, menu_title */
    private function __construct(private readonly \Database $db, private readonly array $settings)
    {
    }

    public static function forSection(\Database $db, int $sectionId): ?self
    {
        $row = $db->fetchRow(
            'SELECT st.*, p.`page_id`, p.`link` AS `page_link`, p.`page_title`, p.`menu_title`
               FROM `{TP}mod_articles_settings` st
               JOIN `{TP}sections` s ON s.`section_id` = st.`section_id`
               JOIN `{TP}pages` p ON p.`page_id` = s.`page_id`
              WHERE st.`section_id` = ?',
            [$sectionId]
        );
        return $row === null ? null : new self($db, $row);
    }

    /** The URL parameters of news_img, validated; anything invalid counts as not given. */
    public static function filters(array $query): array
    {
        $int = static function (string $key, int $min, int $max) use ($query): ?int {
            $value = filter_var($query[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
            return is_int($value) ? $value : null;
        };
        $tags = is_string($query['tags'] ?? null) ? explode(',', $query['tags']) : [];
        return [
            'p'      => $int('p', 0, PHP_INT_MAX) ?? 0,
            'g'      => $int('g', 1, PHP_INT_MAX),
            'tags'   => array_values(array_filter(array_map('trim', $tags), 'strlen')),
            'm'      => $int('m', 1, 12),
            'y'      => $int('y', 1970, 2100),
            'method' => $int('method', 0, 1),
        ];
    }

    /** RFC 3986 query string; null and '' are left out, 0 stays (news_img links "?p=0"). */
    public static function url(string $base, array $params): string
    {
        $query = http_build_query(array_filter($params, static fn ($v): bool => $v !== null && $v !== ''), '', '&', PHP_QUERY_RFC3986);
        return $query === '' ? $base : $base . (str_contains($base, '?') ? '&' : '?') . $query;
    }

    public static function pageUrl(string $link): string
    {
        return WB_URL . PAGES_DIRECTORY . $link . PAGE_EXTENSION;
    }

    public function list(array $query): string
    {
        $s = $this->settings;
        $f = self::filters($query);
        [$where, $params] = $this->where($f, true);
        $perPage = (int) $s['per_page'];
        $rows = $this->db->fetchAll(
            "SELECT i.* FROM `{TP}mod_articles_items` i WHERE $where ORDER BY " . Articles::orderSql((int) $s['sort_order'])
            . ($perPage > 0 ? " LIMIT $perPage OFFSET {$f['p']}" : ''),
            $params
        );
        $this->createMissingAccessFiles($rows);

        $total = $perPage > 0 ? (int) $this->db->fetchValue("SELECT COUNT(*) FROM `{TP}mod_articles_items` i WHERE $where", $params) : 0;
        $pagination = $this->pagination($f, $perPage, $total);
        $items = [];
        foreach ($this->values($rows, $f, false) as $values) {
            $values['DISPLAY_PREVIOUS_NEXT_LINKS'] = $pagination['DISPLAY_PREVIOUS_NEXT_LINKS'];
            $items[] = Placeholders::render((string) $s['tpl_list_item'], $values);
        }
        $this->insertKeywords();

        return $this->wrap(
            Placeholders::render((string) $s['tpl_list_header'], $pagination, Placeholders::PAGINATION)
            . ($items === [] ? \Lang::get('TEXT', 'NONE_FOUND') : implode("\n", $items))
            . Placeholders::render((string) $s['tpl_list_footer'], $pagination, Placeholders::PAGINATION)
        );
    }

    /**
     * List items without paging or filters, for droplets; keyed by article_id.
     * $rows are visible articles of this section.
     *
     * @return array<int, string>
     */
    public function items(array $rows): array
    {
        $this->createMissingAccessFiles($rows);
        $items = [];
        foreach ($this->values($rows, self::filters([]), false) as $values) {
            $values['DISPLAY_PREVIOUS_NEXT_LINKS'] = 'hidden';
            $items[(int) $values['POST_ID']] = Placeholders::render((string) $this->settings['tpl_list_item'], $values);
        }
        return $items;
    }

    /** List header and footer of this section around droplet items, pagination hidden. */
    public function frame(string $items, ?string $view = null): string
    {
        $pagination = $this->pagination(self::filters([]), 0, 0);
        return $this->wrap(
            Placeholders::render((string) $this->settings['tpl_list_header'], $pagination, Placeholders::PAGINATION)
            . $items
            . Placeholders::render((string) $this->settings['tpl_list_footer'], $pagination, Placeholders::PAGINATION),
            $view
        );
    }

    /** Detail page; '' with status 404 if the article is not visible (its access file is outdated). */
    public function detail(int $articleId, array $query): string
    {
        $s = $this->settings;
        $f = self::filters($query);
        $row = $this->db->fetchRow(
            'SELECT i.* FROM `{TP}mod_articles_items` i WHERE i.`article_id` = ? AND i.`section_id` = ? AND ' . Articles::visibleSql('i'),
            [$articleId, (int) $s['section_id']]
        );
        if ($row === null) {
            if (!headers_sent()) {
                http_response_code(404);
            }
            return '';
        }

        // Neighbours in list order, within the group of ?g= as in news_img.
        // shortcut: loads id and link of all visible articles of the section; fine for hundreds, a keyset query once sections hold many thousands
        [$where, $params] = $this->where($f, false);
        $order = $this->db->fetchAll(
            "SELECT i.`article_id`, i.`section_id`, i.`link` FROM `{TP}mod_articles_items` i WHERE $where ORDER BY " . Articles::orderSql((int) $s['sort_order']),
            $params
        );
        $pos = array_search($articleId, array_map('intval', array_column($order, 'article_id')), true);
        $prev = is_int($pos) ? ($order[$pos - 1] ?? null) : null;
        $next = is_int($pos) ? ($order[$pos + 1] ?? null) : null;
        $this->createMissingAccessFiles(array_filter([$prev, $next]));

        $values = $this->values([$row], $f, true)[0];
        $nav = fn (?array $a): string => $a === null ? '' : self::e(self::url(self::pageUrl((string) $a['link']), ['g' => $f['g']]));
        $values['PREVIOUS_LINK'] = $nav($prev);
        $values['NEXT_LINK'] = $nav($next);
        $values['PREVIOUS_PAGE_LINK'] = $prev ? '<a href="' . $values['PREVIOUS_LINK'] . '">' . $values['TEXT_PREV_POST'] . '</a>' : '';
        $values['NEXT_PAGE_LINK'] = $next ? '<a href="' . $values['NEXT_LINK'] . '">' . $values['TEXT_NEXT_POST'] . '</a>' : '';
        $values['DISPLAY_PREVIOUS_NEXT_LINKS'] = ($prev || $next) ? 'visible' : 'hidden';
        $values['IMAGES'] = $this->gallery($articleId);

        if ((int) $s['use_block2'] === 1) {
            $block2 = self::mediaRel(Placeholders::render((string) $row['block2'] !== '' ? (string) $row['block2'] : (string) $s['tpl_block2'], $values));
            // news_img sets all three; the core templates ask for MODULES_BLOCK2
            foreach (['MODULES_BLOCK2', 'NEWS_BLOCK2', 'TOPIC_BLOCK2'] as $constant) {
                defined($constant) or define($constant, $block2);
            }
        }
        $this->insertKeywords();

        // Gallery script before the output, as in news_img
        return $this->galleryInclude()
            . $this->wrap(Placeholders::render($s['tpl_detail_header'] . $s['tpl_detail_content'] . $s['tpl_detail_footer'], $values));
    }

    /** WHERE for list, count and neighbours; date and tag filters only apply to the list. */
    private function where(array $f, bool $listFilters): array
    {
        $sql = ['i.`section_id` = ?', Articles::visibleSql('i')];
        $params = [(int) $this->settings['section_id']];
        if ($f['g'] !== null) {
            $sql[] = 'i.`group_id` = ?';
            $params[] = $f['g'];
        }
        if ($listFilters && $f['m'] !== null && $f['y'] !== null && $f['method'] !== null) {
            // method 0 was posted_when in news_img, imported as modified_at
            $column = $f['method'] === 1 ? 'published_from' : 'modified_at';
            $sql[] = "i.`$column` >= ? AND i.`$column` < ?";
            array_push($params, ...self::monthRange($f['m'], $f['y']));
        }
        if ($listFilters && $f['tags'] !== []) {
            $sql[] = 'i.`article_id` IN (SELECT it.`article_id` FROM `{TP}mod_articles_item_tags` it'
                . ' JOIN `{TP}mod_articles_tags` t ON t.`tag_id` = it.`tag_id`'
                . ' WHERE t.`title` IN (' . implode(',', array_fill(0, count($f['tags']), '?')) . '))';
            array_push($params, ...$f['tags']);
        }
        return [implode(' AND ', $sql), $params];
    }

    /**
     * Start and end (exclusive) of a month in the time shown on the website:
     * dates are displayed with TIMEZONE added, so the bounds subtract it.
     *
     * @return array{int, int}
     */
    public static function monthRange(int $month, int $year): array
    {
        return [gmmktime(0, 0, 0, $month, 1, $year) - (int) TIMEZONE, gmmktime(0, 0, 0, $month + 1, 1, $year) - (int) TIMEZONE];
    }

    /** Values of the pagination placeholders, texts and quirks as in news_img. */
    private function pagination(array $f, int $perPage, int $total): array
    {
        $empty = array_fill_keys(Placeholders::PAGINATION, '');
        if ($perPage === 0) {
            return ['DISPLAY_PREVIOUS_NEXT_LINKS' => 'hidden'] + $empty;
        }
        $p = $f['p'];
        $link = static fn (int $to, string $text): string => '<a href="'
            . self::e(self::url('', ['p' => $to, 'tags' => implode(',', $f['tags']), 'g' => $f['g']])) . '">' . $text . '</a>';
        $range = ($p + 1) . '-' . min($p + $perPage, $total);
        $values = [
            'PREVIOUS_LINK'      => $p > 0 ? $link(max(0, $p - $perPage), \Lang::get('TEXT', 'PREVIOUS')) : '',
            'PREVIOUS_PAGE_LINK' => $p > 0 ? $link(max(0, $p - $perPage), \Lang::get('TEXT', 'PREVIOUS_PAGE')) : '',
            'NEXT_LINK'          => $p + $perPage < $total ? $link($p + $perPage, \Lang::get('TEXT', 'NEXT')) : '',
            'NEXT_PAGE_LINK'     => $p + $perPage < $total ? $link($p + $perPage, \Lang::get('TEXT', 'NEXT_PAGE')) : '',
            'OUT_OF'             => $range . ' ' . mb_strtolower(\Lang::get('TEXT', 'OUT_OF')) . ' ' . $total,
            'OF'                 => $range . ' ' . mb_strtolower(\Lang::get('TEXT', 'OF')) . ' ' . $total,
        ];
        $values['DISPLAY_PREVIOUS_NEXT_LINKS'] = ($values['PREVIOUS_LINK'] !== '' || $values['NEXT_LINK'] !== '') ? 'visible' : 'hidden';
        return $values;
    }

    /**
     * Placeholder values per article. Text values are escaped, HTML fields
     * (contents, images, tags, links as markup) stay raw.
     *
     * @return list<array<string, string|int>>
     */
    private function values(array $rows, array $f, bool $detail): array
    {
        if ($rows === []) {
            return [];
        }
        $s = $this->settings;
        $sectionId = (int) $s['section_id'];
        $ids = array_map(static fn (array $r): int => (int) $r['article_id'], $rows);
        $in = implode(',', $ids);
        $tags = $this->tagsOf($in);
        $galleryCounts = array_column(
            $this->db->fetchAll("SELECT `article_id`, COUNT(*) AS `n` FROM `{TP}mod_articles_images` WHERE `article_id` IN ($in) GROUP BY `article_id`"),
            'n',
            'article_id'
        );
        $groups = array_column(
            $this->db->fetchAll('SELECT `group_id`, `title`, `image` FROM `{TP}mod_articles_groups` WHERE `section_id` = ?', [$sectionId]),
            null,
            'group_id'
        );
        $users = $this->users(array_column($rows, 'created_by'));
        $pageUrl = self::pageUrl((string) $s['page_link']);
        $listParams = ['p' => $f['p'] ?: null, 'g' => $f['g']];
        $texts = [];
        foreach (self::TEXTS as $key) {
            $texts[$key] = \Lang::get('MOD_ARTICLES', $key);
        }
        $defaultImage = $s['default_image'] !== '' ? self::mediaUrl("/sections/$sectionId/preview/" . $s['default_image']) : '';
        $unknown = '<i>' . \Lang::get('MOD_ARTICLES', 'UNKNOWN_USER') . '</i>';

        $all = [];
        foreach ($rows as $r) {
            $id = (int) $r['article_id'];
            $title = self::e((string) $r['title'], false);
            $group = $groups[$r['group_id']] ?? null;
            $groupTitle = $group ? self::e((string) $group['title'], false) : '';
            $groupImage = $group && $group['image'] !== '' ? self::mediaUrl("/groups/{$group['group_id']}/preview/{$group['image']}") : '';
            $imageUrl = $r['image'] !== '' ? self::mediaUrl("/items/$id/preview/{$r['image']}") : '';
            // Cascade as in news_img v6.0: article, group, section default; else a tile in lists, nothing on the detail page.
            $shown = $imageUrl ?: $groupImage ?: $defaultImage;
            $image = match (true) {
                $shown !== '' => "<img src='" . self::e($shown) . "' alt='$title' />",
                $detail       => '',
                default       => self::noImage((string) $r['title']),
            };
            // news_img: long text of 9+ characters (an empty paragraph is shorter) or gallery images
            $hasDetail = strlen(trim((string) $r['content'])) >= 9 || isset($galleryCounts[$id]);
            $link = self::e(self::url(self::pageUrl((string) $r['link']), $listParams));
            $published = (int) $r['published_from'] ?: (int) $r['created_at'];
            $modified = max((int) $r['published_from'], (int) $r['modified_at']);
            $user = $users[(int) $r['created_by']] ?? null;
            $tagSpans = [];
            foreach ($tags[$id] ?? [] as $i => $tag) {
                $this->keywords[$tag['title']] = true;
                $tagSpans[] = '<span class="mod_articles_tag" id="mod_articles_tag_' . $id . '_' . $i . '"' . Tags::styleAttr($tag) . '>'
                    . '<a href="' . self::e(self::url($pageUrl, ['tags' => $tag['title']])) . '">' . self::e((string) $tag['title'], false) . '</a></span>';
            }

            $all[] = $texts + [
                'POST_ID'             => $id,
                'TITLE'               => $title,
                'PAGE_TITLE'          => self::e((string) ($s['page_title'] ?: $s['menu_title']), false),
                'BACK'                => self::e(self::url($pageUrl, $listParams)),
                'LINK'                => $link,
                'HREF'                => $hasDetail ? 'href="' . $link . '"' : '',
                'AOPEN'               => $hasDetail ? '<a href="' . $link . '">' : '',
                'ACLOSE'              => $hasDetail ? '</a>' : '',
                'SHOW_READ_MORE'      => $hasDetail ? 'visible' : 'hidden',
                'IMAGE'               => $image,
                'POST_OR_GROUP_IMAGE' => $image,
                'IMAGE_URL'           => self::e($imageUrl),
                'GROUP_ID'            => (int) $r['group_id'],
                'GROUP_TITLE'         => $groupTitle,
                'GROUP_IMAGE'         => $groupImage === '' ? '' : "<img class='mod_articles_grouppic' src='" . self::e($groupImage)
                    . "' alt='$groupTitle' title='" . self::e(\Lang::get('TEXT', 'GROUP')) . ": $groupTitle' />",
                'GROUP_IMAGE_URL'     => self::e($groupImage),
                'DISPLAY_IMAGE'       => $groupImage === '' ? 'none' : 'inherit',
                'DISPLAY_GROUP'       => (int) $r['group_id'] === 0 ? 'none' : 'inherit',
                'SHORT'               => $r['teaser'],
                'CONTENT_SHORT'       => $r['teaser'],
                'CONTENT_LONG'        => $r['content'],
                'CONTENT'             => $r['teaser'] . $r['content'],
                'CONTENT_BLOCK2'      => $r['block2'],
                'PUBLISHED_DATE'      => self::date(DATE_FORMAT, $published),
                'PUBLISHED_TIME'      => self::date(TIME_FORMAT, $published),
                'MODI_DATE'           => self::date(DATE_FORMAT, $modified),
                'MODI_TIME'           => self::date(TIME_FORMAT, $modified),
                'CREATED_DATE'        => self::date(DATE_FORMAT, (int) $r['created_at']),
                'CREATED_TIME'        => self::date(TIME_FORMAT, (int) $r['created_at']),
                'USER_ID'             => (int) $r['created_by'],
                'DISPLAY_NAME'        => $user ? self::e((string) $user['display_name'], false) : $unknown,
                'USERNAME'            => $user ? self::e((string) $user['username'], false) : $unknown,
                'EMAIL'               => $user ? self::e((string) $user['email'], false) : '',
                'TAGS'                => implode(' ', $tagSpans),
                'TAGLIST'             => self::e(implode(',', array_column($tags[$id] ?? [], 'title')), false),
            ];
        }
        return $all;
    }

    /** @return array<int, list<array>> tags per article, sorted naturally by title */
    private function tagsOf(string $in): array
    {
        $byArticle = [];
        foreach ($this->db->fetchAll(
            "SELECT it.`article_id`, t.`title`, t.`color`, t.`text_color`
               FROM `{TP}mod_articles_item_tags` it JOIN `{TP}mod_articles_tags` t ON t.`tag_id` = it.`tag_id`
              WHERE it.`article_id` IN ($in)"
        ) as $tag) {
            $byArticle[(int) $tag['article_id']][] = $tag;
        }
        foreach ($byArticle as &$tags) {
            usort($tags, static fn (array $a, array $b): int => strnatcasecmp((string) $a['title'], (string) $b['title']));
        }
        return $byArticle;
    }

    /** @return array<int, array> users by id */
    private function users(array $ids): array
    {
        $ids = array_unique(array_map('intval', $ids));
        return array_column($this->db->fetchAll(
            'SELECT `user_id`, `username`, `display_name`, `email` FROM `{TP}users` WHERE `user_id` IN (' . implode(',', $ids) . ')'
        ), null, 'user_id');
    }

    private function gallery(int $articleId): string
    {
        $s = $this->settings;
        $html = '';
        foreach ($this->db->fetchAll(
            'SELECT `file`, `caption` FROM `{TP}mod_articles_images` WHERE `article_id` = ? ORDER BY `position`, `image_id`',
            [$articleId]
        ) as $image) {
            $html .= Placeholders::renderImage((string) $s['tpl_image_loop'], [
                'IMAGE'       => self::e(self::mediaBase() . "/items/$articleId/large/" . $image['file']),
                'THUMB'       => self::e(self::mediaBase() . "/items/$articleId/thumb/" . $image['file']),
                'DESCRIPTION' => self::e((string) $image['caption'], false),
                'THUMBWIDTH'  => (int) $s['thumb_width'],
                'THUMBHEIGHT' => (int) $s['thumb_height'],
                'WB_URL'      => WB_URL,
            ]);
        }
        return $html;
    }

    /** Script and style of the gallery; the name comes from the whitelist only. */
    private function galleryInclude(): string
    {
        $gallery = (string) $this->settings['gallery'];
        if (!in_array($gallery, Settings::GALLERIES, true)) {
            return '';
        }
        ob_start();
        include dirname(__DIR__) . "/js/galleries/$gallery/include.tpl";
        return (string) ob_get_clean();
    }

    private function wrap(string $html, ?string $view = null): string
    {
        $view = Settings::view($view ?? (string) $this->settings['view']);
        if (class_exists('I', false)) {
            \I::insertCssFile(WB_URL . "/modules/articles/views/$view/frontend.css", 'head_late', [], "articles_view_$view");
        }
        return '<div class="mod_articles_' . $view . '">' . "\n" . self::mediaRel($html) . '</div>';
    }

    private function createMissingAccessFiles(array $rows): void
    {
        foreach ($rows as $row) {
            if (!is_file(AccessFiles::path((string) $row['link']))) {
                AccessFiles::write($row, (int) $this->settings['page_id']);
            }
        }
    }

    private function insertKeywords(): void
    {
        if ($this->keywords !== [] && class_exists('I', false)) {
            \I::insertMetaTag(['name' => 'keywords', 'content' => implode(', ', array_keys($this->keywords))]);
        }
    }

    /** URL of a file below media/.articles, '' if it does not exist. */
    public static function mediaUrl(string $path): string
    {
        return is_file(\ARTICLES_MEDIA_PATH . $path) ? self::mediaBase() . $path : '';
    }

    private static function mediaBase(): string
    {
        return WB_URL . MEDIA_DIRECTORY . '/.articles';
    }

    private static function mediaRel(string $html): string
    {
        return str_replace('{SYSVAR:MEDIA_REL}', WB_URL . MEDIA_DIRECTORY, $html);
    }

    /** Tile with the initial for lists without any image (news_img v6.0); inline so every view gets it. */
    private static function noImage(string $title): string
    {
        return '<span class="mod_articles_noimage" aria-hidden="true"'
            . ' style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;min-height:80px;'
            . 'background:#314B68;color:#fff;font-size:2.5em;font-weight:500;border-radius:3px;">'
            . self::e(mb_strtoupper(mb_substr(trim($title), 0, 1))) . '</span>';
    }

    /** The core's frontend dates: format of the user, TIMEZONE as offset. */
    private static function date(string $format, int $timestamp): string
    {
        return date($format, $timestamp + (int) TIMEZONE);
    }

    private static function e(string $value, bool $doubleEncode = true): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', $doubleEncode);
    }
}
