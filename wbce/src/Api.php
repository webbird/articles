<?php
/**
 * articles – Api
 * Entry for droplets: article lists (counterpart of fetchNewsItems) and the slider.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Api
{
    /** Options of fetchNewsItems, so existing droplet calls only need the new name. */
    public const LIST_OPTIONS = [
        'group_id_type'     => 'group_id',     // group_id, section_id, page_id or post_id (= article_id)
        'group_id'          => '0',            // comma separated ids of that type, 0 = all
        'start_news_item'   => 0,              // offset; negative counts from the end
        'max_news_items'    => 10,             // 1 to 999
        'max_news_length'   => -1,             // shortens the teaser to plain text of this length, -1 = full
        'strip_tags'        => true,
        'allowed_tags'      => '<p><a><img>',
        'sort_by'           => 1,              // 1 position, 2 last change, 3 published from, 4 random, 5 published until
        'sort_order'        => 1,              // 1 descending, 2 ascending
        'not_older_than'    => 0,              // days, published from at most that long ago; 0 = off
        'is_not_older_than' => 0,              // alias of not_older_than
        'lang_id'           => 'AUTO',
        'lang_filter'       => false,          // only pages in language lang_id (AUTO = current language)
        'skip'              => '',             // comma separated tags to leave out
        'tags'              => '',             // comma separated tags, at least one must match
        'groups_on_tags'    => false,          // keep the group_id filter when tags or skip are given
        'view'              => '',             // CSS view; empty = view of the first article's section
        'aslist'            => false,          // only titles as <ul>
    ];

    private const SORT = [1 => 'position', 2 => 'modified_at', 3 => 'published_from', 4 => 'random', 5 => 'published_until'];
    private const ID_TYPES = ['group_id' => 'i.`group_id`', 'section_id' => 'i.`section_id`', 'page_id' => 's.`page_id`', 'post_id' => 'i.`article_id`'];

    public static function list(\Database $db, array $options): string
    {
        $o = array_intersect_key($options, self::LIST_OPTIONS) + self::LIST_OPTIONS;
        // Droplet parameters arrive as strings, sometimes quoted
        $o = array_map(static fn ($v) => is_string($v) ? trim($v, " \"'") : $v, $o);
        $bool = static fn ($v): bool => filter_var($v, FILTER_VALIDATE_BOOL);
        $int = static fn ($v, int $default, int $min, int $max): int
            => max($min, min($max, filter_var($v, FILTER_VALIDATE_INT) === false ? $default : (int) $v));

        $where = [Articles::visibleSql('i'), Articles::publicSql()];
        $params = [];
        $ids = array_map('intval', explode(',', (string) $o['group_id']));
        $tags = self::tagList((string) ($o['tags'] !== '' ? $o['tags'] : ($_GET['tags'] ?? ''))); // news_img follows the page's tag filter
        $skip = self::tagList((string) $o['skip']);
        $byGroup = $tags === [] && $skip === [] || $bool($o['groups_on_tags']);
        if (!in_array(0, $ids, true) && $byGroup) {
            $where[] = (self::ID_TYPES[$o['group_id_type']] ?? self::ID_TYPES['group_id']) . ' IN (' . implode(',', $ids) . ')';
        }
        $days = (float) ($o['is_not_older_than'] ?: $o['not_older_than']);
        if ($days > 0) {
            $where[] = 'i.`published_from` >= ?';
            $params[] = time() - (int) ($days * 86400);
        }
        if ($bool($o['lang_filter'])) {
            $where[] = 'p.`language` = ?';
            $params[] = strtoupper($o['lang_id'] === 'AUTO' && defined('LANGUAGE') ? LANGUAGE : (string) $o['lang_id']);
        }
        foreach (['IN' => $tags, 'NOT IN' => $skip] as $op => $list) {
            if ($list !== []) {
                $where[] = "i.`article_id` $op (SELECT it.`article_id` FROM `{TP}mod_articles_item_tags` it"
                    . ' JOIN `{TP}mod_articles_tags` t ON t.`tag_id` = it.`tag_id`'
                    . ' WHERE t.`title` IN (' . implode(',', array_fill(0, count($list), '?')) . '))';
                array_push($params, ...$list);
            }
        }

        $sort = self::SORT[$int($o['sort_by'], 1, 1, 5)];
        $direction = $int($o['sort_order'], 1, 1, 2) === 1 ? 'DESC' : 'ASC';
        $rows = $db->fetchAll(
            'SELECT i.* FROM `{TP}mod_articles_items` i
               JOIN `{TP}sections` s ON s.`section_id` = i.`section_id`
               JOIN `{TP}pages` p ON p.`page_id` = s.`page_id`
              WHERE s.`module` = \'articles\' AND ' . implode(' AND ', $where)
            . ($sort === 'random' ? '' : " ORDER BY i.`$sort` $direction, i.`article_id` $direction"),
            $params
        );
        // shortcut: filters and slices in PHP (random order, offset from the end); a LIMIT in SQL once lists get long
        if ($sort === 'random') {
            shuffle($rows);
        }
        $rows = array_slice($rows, $int($o['start_news_item'], 0, -999, 999), $int($o['max_news_items'], 10, 1, 999));
        if ($rows === []) {
            return '';
        }

        if ($bool($o['aslist'])) {
            $items = array_map(static fn (array $r): string => '<li class="mod_articles_list_item"><a href="'
                . htmlspecialchars(Frontend::pageUrl((string) $r['link']), ENT_QUOTES | ENT_HTML5) . '">'
                . htmlspecialchars((string) $r['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8', false) . '</a></li>', $rows);
            return "<ul class=\"mod_articles_list\">\n" . implode("\n", $items) . "\n</ul>";
        }

        $maxLength = $int($o['max_news_length'], -1, -1, 9999);
        foreach ($rows as &$row) {
            if ($bool($o['strip_tags'])) {
                $row['teaser'] = strip_tags((string) $row['teaser'], (string) $o['allowed_tags']);
                $row['content'] = strip_tags((string) $row['content'], (string) $o['allowed_tags']);
            }
            $plain = trim(html_entity_decode(strip_tags((string) $row['teaser']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($maxLength >= 0 && mb_strlen($plain) > $maxLength) {
                $row['teaser'] = htmlspecialchars(mb_substr($plain, 0, $maxLength), ENT_QUOTES | ENT_HTML5) . '…';
            }
        }
        unset($row);

        // Each section renders its articles with its own templates, the order stays.
        $html = [];
        $first = null;
        foreach (array_unique(array_column($rows, 'section_id')) as $sectionId) {
            $frontend = Frontend::forSection($db, (int) $sectionId);
            if ($frontend === null) {
                continue;
            }
            $first ??= $frontend;
            $html += $frontend->items(array_values(array_filter($rows, static fn (array $r): bool => $r['section_id'] == $sectionId)));
        }
        if ($first === null) {
            return '';
        }
        $items = array_filter(array_map(static fn (array $r): ?string => $html[(int) $r['article_id']] ?? null, $rows));
        return $first->frame(implode("\n", $items), $o['view'] !== '' ? (string) $o['view'] : null);
    }

    /**
     * Slider from the visible articles of a section with a preview image.
     * $tpl names templates/slides/<tpl>.txt; it gets $posts (title escaped,
     * image_url of the original, teaser and content as HTML, link).
     */
    public static function slider(\Database $db, int $sectionId, string $tpl = 'bs5carousel'): string
    {
        $tpl = $tpl === '' ? 'bs5carousel' : $tpl;
        $file = dirname(__DIR__) . "/templates/slides/$tpl.txt";
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $tpl) || !is_file($file)) {
            return '';
        }
        $sortOrder = (int) $db->fetchValue('SELECT `sort_order` FROM `{TP}mod_articles_settings` WHERE `section_id` = ?', [$sectionId]);
        $posts = [];
        foreach ($db->fetchAll(
            'SELECT i.* FROM `{TP}mod_articles_items` i
               JOIN `{TP}sections` s ON s.`section_id` = i.`section_id`
               JOIN `{TP}pages` p ON p.`page_id` = s.`page_id`
              WHERE i.`section_id` = ? AND ' . Articles::visibleSql('i') . ' AND ' . Articles::publicSql()
            . ' ORDER BY ' . Articles::orderSql($sortOrder),
            [$sectionId]
        ) as $r) {
            $original = '/items/' . (int) $r['article_id'] . '/' . $r['image'];
            $posts[] = [
                'article_id'    => (int) $r['article_id'],
                'title'         => htmlspecialchars((string) $r['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8', false),
                'image'         => (string) $r['image'],
                'image_url'     => $r['image'] !== '' && is_file(\ARTICLES_MEDIA_PATH . $original)
                    ? htmlspecialchars(WB_URL . MEDIA_DIRECTORY . '/.articles' . $original, ENT_QUOTES | ENT_HTML5) : '',
                'content_short' => (string) $r['teaser'],
                'content_long'  => (string) $r['content'],
                'link'          => htmlspecialchars(Frontend::pageUrl((string) $r['link']), ENT_QUOTES | ENT_HTML5),
            ];
        }
        if ($posts === []) {
            return '';
        }
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /** @return list<string> */
    private static function tagList(string $tags): array
    {
        return array_values(array_filter(array_map('trim', explode(',', urldecode($tags))), 'strlen'));
    }
}
