<?php
/**
 * articles – Backend
 * Masks of modify.php (tabs, article editor, settings) and the helpers that
 * backend.php shares: texts, Twig, URLs, flash messages, form values.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Backend
{
    public const TABS = ['articles', 'groups', 'tags', 'maintenance'];
    public const STATUSES = ['visible', 'inactive', 'planned', 'expired', 'hidden'];
    public const BULK = ['activate', 'deactivate', 'delete', 'copy', 'copy_with_tags', 'move', 'move_with_tags', 'group', 'tags', 'clear_from', 'clear_until'];

    public static function t(string $key, string|int ...$args): string
    {
        $text = \Lang::get('MOD_ARTICLES', $key, $key);
        return $args === [] ? $text : vsprintf($text, $args);
    }

    /** WBCE's getTwig() turns autoescaping off; the templates escape by default. */
    public static function twig(): \Twig\Environment
    {
        $twig = getTwig(dirname(__DIR__) . '/twig/');
        $twig->getExtension(\Twig\Extension\EscaperExtension::class)->setDefaultStrategy('html');
        $twig->addFunction(new \Twig\TwigFunction('t', self::t(...)));
        return $twig;
    }

    /** Backend URL of the section; parameters only count for this section (art_section). */
    public static function url(int $pageId, int $sectionId, array $params = []): string
    {
        // settings and editor have a page of their own, without the other sections of the page
        $own = ['settings' => 'settings.php', 'edit' => 'article.php'][$params['art_view'] ?? ''] ?? null;
        if ($own !== null) {
            $query = ['page_id' => $pageId, 'section_id' => $sectionId] + array_intersect_key($params, ['art_id' => 0]);
            return WB_URL . "/modules/articles/$own?" . http_build_query($query, '', '&');
        }
        $query = ['page_id' => $pageId] + ($params === [] ? [] : ['art_section' => $sectionId] + $params);
        $anchor = '#' . (defined('SEC_ANCHOR') && SEC_ANCHOR ? SEC_ANCHOR : 'sec_anchor_') . $sectionId;
        return ADMIN_URL . '/pages/modify.php?' . http_build_query($query, '', '&') . $anchor;
    }

    public static function flash(int $sectionId, array $data): void
    {
        $_SESSION['mod_articles_flash'][$sectionId] = $data;
    }

    public static function takeFlash(int $sectionId): array
    {
        $data = $_SESSION['mod_articles_flash'][$sectionId] ?? [];
        unset($_SESSION['mod_articles_flash'][$sectionId]);
        return is_array($data) ? $data : [];
    }

    /** "2026-10-01T12:00" in the backend user's time (TIMEZONE offset) <=> timestamp; '' <=> 0. */
    public static function toTimestamp(string $value): ?int
    {
        if ($value === '') {
            return 0;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new \DateTimeZone('UTC'));
        // createFromFormat() rolls month 13 over into the next year; only an exact round trip is valid
        return $date === false || $date->format('Y-m-d\TH:i') !== $value ? null : $date->getTimestamp() - (int) TIMEZONE;
    }

    public static function fromTimestamp(int $timestamp): string
    {
        return $timestamp === 0 ? '' : gmdate('Y-m-d\TH:i', $timestamp + (int) TIMEZONE);
    }

    /** Output of modify.php for one section. */
    public static function render(\Database $db, \Admin $admin, int $pageId, int $sectionId): string
    {
        // WBCE can register a section without add.php (e.g. after an addon reload)
        if (Settings::get($db, $sectionId) === null) {
            Settings::create($db, $sectionId);
        }
        $param = static fn (string $name): string => (int) ($_GET['art_section'] ?? 0) === $sectionId && is_string($_GET[$name] ?? null) ? $_GET[$name] : '';
        $access = new Access($db, $admin);
        $flash = self::takeFlash($sectionId);
        $vars = [
            'page_id'    => $pageId,
            'section_id' => $sectionId,
            // ids in the URL as well: a POST above post_max_size arrives without its fields (backend.php)
            'action'     => WB_URL . '/modules/articles/backend.php?' . http_build_query(['page_id' => $pageId, 'section_id' => $sectionId]),
            'errors'     => array_merge($flash['errors'] ?? [], $param('art_error') === 'post_too_large' ? [self::t('ERR_POST_TOO_LARGE', (string) ini_get('post_max_size'))] : []),
            'success'    => $flash['success'] ?? '',
            'notes'      => $flash['notes'] ?? [],
            'old'        => is_array($flash['old'] ?? null) ? $flash['old'] : null,
            'can_settings' => $access->settings($sectionId),
            'url_list'   => self::url($pageId, $sectionId),
            'url_settings' => self::url($pageId, $sectionId, ['art_view' => 'settings']),
            'url_new'    => self::url($pageId, $sectionId, ['art_view' => 'edit']),
            // docs/ in the markdown reader of WBCE (core module MarkdownWbce), tabs from docs/md_reader.json
            'help_link'  => class_exists('MdReaderLink')
                ? \MdReaderLink::dir('/modules/articles/docs/')->title('articles')->linkHtml(self::t('HELP'), 'art-button')
                : '',
        ];

        return match ($param('art_view')) {
            'edit'     => self::editor($db, $access, $vars, (int) $param('art_id')),
            'settings' => $vars['can_settings'] ? self::settings($db, $access, $vars) : self::tabs($db, $access, $vars, 'articles', $param),
            default    => self::tabs($db, $access, $vars, in_array($param('art_tab'), self::TABS, true) ? $param('art_tab') : 'articles', $param),
        };
    }

    private static function tabs(\Database $db, Access $access, array $vars, string $tab, \Closure $param): string
    {
        $sectionId = $vars['section_id'];
        $pageId = $vars['page_id'];
        $settings = Settings::get($db, $sectionId);
        $groups = Groups::list($db, $sectionId);
        $tags = Tags::forSection($db, $sectionId);
        $groupActive = array_column(array_map(static fn (array $g): array => [(int) $g['group_id'], (int) $g['active'] === 1], $groups), 1, 0);
        $groupTitle = array_column($groups, 'title', 'group_id');
        $tabUrl = static fn (string $t, array $more = []): string => self::url($pageId, $sectionId, ['art_tab' => $t] + $more);

        $filter = [
            'group'  => (int) $param('art_group'),
            'status' => in_array($param('art_status'), self::STATUSES, true) ? $param('art_status') : '',
            'q'      => trim($param('art_q')),
        ];
        $now = time();
        $rows = [];
        foreach ($db->fetchAll(
            'SELECT i.`article_id`, i.`group_id`, i.`position`, i.`active`, i.`title`, i.`link`, i.`published_from`, i.`published_until`,
                    (SELECT COUNT(*) FROM `{TP}mod_articles_item_tags` it WHERE it.`article_id` = i.`article_id`) AS `tags`
               FROM `{TP}mod_articles_items` i WHERE i.`section_id` = ? ORDER BY ' . Articles::orderSql((int) $settings['sort_order']),
            [$sectionId]
        ) as $a) {
            $status = Articles::status($a, $groupActive[(int) $a['group_id']] ?? true, $now);
            if (($filter['group'] !== 0 && (int) $a['group_id'] !== ($filter['group'] === -1 ? 0 : $filter['group']))
                || ($filter['status'] !== '' && $status !== $filter['status'])
                || ($filter['q'] !== '' && mb_stripos((string) $a['title'], $filter['q']) === false)) {
                continue;
            }
            $rows[] = [
                'id'       => (int) $a['article_id'],
                'title'    => (string) $a['title'],
                'link'     => (string) $a['link'],
                'url_view' => Frontend::pageUrl((string) $a['link']),
                'group'    => $groupTitle[$a['group_id']] ?? '',
                'tags'     => (int) $a['tags'],
                'from'     => (int) $a['published_from'] ? self::date((int) $a['published_from']) : '',
                'until'    => (int) $a['published_until'] ? self::date((int) $a['published_until']) : '',
                'status'   => $status,
                'active'   => (int) $a['active'] === 1,
                'url_edit' => self::url($pageId, $sectionId, ['art_view' => 'edit', 'art_id' => (int) $a['article_id']]),
            ];
        }
        $filtered = $filter['group'] !== 0 || $filter['status'] !== '' || $filter['q'] !== '';

        $editTag = null;
        foreach ($tags as $tag) {
            if ($tab === 'tags' && (int) $tag['tag_id'] === (int) $param('art_id')) {
                $editTag = $tag;
            }
        }
        $editGroup = null;
        foreach ($groups as $group) {
            if ($tab === 'groups' && (int) $group['group_id'] === (int) $param('art_id')) {
                $editGroup = $group;
            }
        }

        return self::twig()->render('modify.twig', $vars + [
            'tab'       => $tab,
            'tab_urls'  => array_combine(self::TABS, array_map($tabUrl, self::TABS)),
            'counts'    => ['articles' => (int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]), 'groups' => count($groups), 'tags' => count($tags)],
            'articles'  => $rows,
            'filter'    => $filter,
            'filtered'  => $filtered,
            'sortable'  => (int) $settings['sort_order'] === 0 && !$filtered,
            'statuses'  => self::STATUSES,
            'bulk'      => self::BULK,
            'targets'   => $access->targets(),
            'groups'    => array_map(static fn (array $g): array => [
                'id' => (int) $g['group_id'], 'title' => (string) $g['title'], 'active' => (int) $g['active'] === 1,
                'articles' => (int) $g['articles'], 'url_edit' => $tabUrl('groups', ['art_id' => (int) $g['group_id']]),
                'image' => self::imageInfo('/groups/' . (int) $g['group_id'], (string) $g['image']),
            ], $groups),
            'tags'      => array_map(static fn (array $t): array => [
                'id' => (int) $t['tag_id'], 'title' => (string) $t['title'], 'color' => (string) $t['color'], 'text_color' => (string) $t['text_color'],
                'style' => Tags::styleAttr($t), 'global' => (bool) $t['global'], 'articles' => (int) $t['articles'],
                'editable' => !$t['global'] || $access->isAdmin(), 'url_edit' => $tabUrl('tags', ['art_id' => (int) $t['tag_id']]),
            ], $tags),
            'is_admin'  => $access->isAdmin(),
            'group_form' => $vars['old'] ?? ['group_id' => (int) ($editGroup['group_id'] ?? 0), 'title' => $editGroup['title'] ?? '', 'active' => (int) ($editGroup['active'] ?? 1) === 1],
            'group_image' => $editGroup !== null ? self::imageInfo('/groups/' . (int) $editGroup['group_id'], (string) $editGroup['image']) : null,
            'max_upload_mb' => self::mb((int) $settings['max_upload']),
            'tag_form'  => $vars['old'] ?? [
                'tag_id' => (int) ($editTag['tag_id'] ?? 0), 'title' => $editTag['title'] ?? '', 'color' => $editTag['color'] ?? '',
                'text_color' => $editTag['text_color'] ?? '', 'global' => (bool) ($editTag['global'] ?? false),
            ],
            'url_tab'   => $tabUrl($tab),
            'maintenance' => $tab === 'maintenance' ? self::maintenance($db, $access, $sectionId) : [],
        ]);
    }

    private static function maintenance(\Database $db, Access $access, int $sectionId): array
    {
        $now = time();
        $missing = 0;
        $orphaned = 0;
        foreach ($db->fetchAll(
            'SELECT i.`link`, (' . Articles::visibleSql('i', $now) . ') AS `visible` FROM `{TP}mod_articles_items` i WHERE i.`section_id` = ?',
            [$sectionId]
        ) as $a) {
            $exists = is_file(AccessFiles::path((string) $a['link']));
            $missing += (int) ((int) $a['visible'] === 1 && !$exists);
            $orphaned += (int) ((int) $a['visible'] !== 1 && $exists);
        }
        $empty = !(int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId])
            && !(int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_groups` WHERE `section_id` = ?', [$sectionId]);
        // Import sources by module, value "module:section"; only for an empty section
        $sources = [];
        if ($empty && (int) $db->fetchValue("SELECT COUNT(*) FROM `{TP}addons` WHERE `directory` = 'news_img'")) {
            foreach ($db->fetchAll(
                "SELECT s.`section_id`, p.`menu_title`, p.`page_title`,
                        (SELECT COUNT(*) FROM `{TP}mod_news_img_posts` n WHERE n.`section_id` = s.`section_id`) AS `posts`
                   FROM `{TP}sections` s JOIN `{TP}pages` p ON p.`page_id` = s.`page_id`
                  WHERE s.`module` = 'news_img' ORDER BY p.`page_trail`, s.`position`"
            ) as $s) {
                $sources['news_img'][] = ['value' => 'news_img:' . (int) $s['section_id'], 'label' => ($s['menu_title'] ?: $s['page_title']) . ' (#' . (int) $s['section_id'] . ', ' . (int) $s['posts'] . ')'];
            }
        }
        if ($empty) {
            foreach ($db->fetchAll('SELECT DISTINCT `module` FROM `{TP}sections`') as $row) {
                $module = (string) $row['module'];
                if (!Import::isTopics($db, $module)) {
                    continue;
                }
                foreach ($db->fetchAll(
                    "SELECT s.`section_id`, p.`menu_title`, p.`page_title`,
                            (SELECT COUNT(*) FROM `{TP}mod_$module` t WHERE t.`section_id` = s.`section_id`) AS `posts`
                       FROM `{TP}sections` s JOIN `{TP}pages` p ON p.`page_id` = s.`page_id`
                      WHERE s.`module` = ? ORDER BY p.`page_trail`, s.`position`",
                    [$module]
                ) as $s) {
                    $sources[$module][] = ['value' => "$module:" . (int) $s['section_id'], 'label' => ($s['menu_title'] ?: $s['page_title']) . ' (#' . (int) $s['section_id'] . ', ' . (int) $s['posts'] . ')'];
                }
            }
            foreach ($access->targets() as $target) {
                $count = (int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$target['section_id']]);
                if ($target['section_id'] !== $sectionId && $count > 0) {
                    $sources['articles'][] = ['value' => 'articles:' . $target['section_id'], 'label' => substr($target['label'], 0, -1) . ", $count)"];
                }
            }
        }
        $images = Images::ofSection($db, $sectionId);
        return [
            'missing' => $missing, 'orphaned' => $orphaned, 'empty' => $empty, 'sources' => $sources, 'packs' => $empty ? Import::demoPacks() : [],
            'images' => count($images), 'outside' => count(Articles::outsideDir($db, $sectionId)),
            'dir' => '/' . AccessFiles::dir($db, $sectionId, Articles::pageOf($db, $sectionId)) . '/',
        ];
    }

    private static function editor(\Database $db, Access $access, array $vars, int $articleId): string
    {
        $sectionId = $vars['section_id'];
        $article = $articleId > 0 ? $db->fetchRow(
            'SELECT * FROM `{TP}mod_articles_items` WHERE `article_id` = ? AND `section_id` = ?',
            [$articleId, $sectionId]
        ) : null;
        $settings = Settings::get($db, $sectionId);
        $form = $vars['old'] ?? ($article === null ? [
            'article_id' => 0, 'title' => '', 'slug' => '', 'slug_locked' => false, 'active' => true,
            'published_from' => '', 'published_until' => '', 'place' => "$sectionId:0",
            'tags' => [], 'teaser' => '', 'content' => '', 'block2' => '',
        ] : [
            'article_id'      => (int) $article['article_id'],
            'title'           => (string) $article['title'],
            'slug'            => Articles::slugOf($article),
            'slug_locked'     => true,
            'active'          => (int) $article['active'] === 1,
            'published_from'  => self::fromTimestamp((int) $article['published_from']),
            'published_until' => self::fromTimestamp((int) $article['published_until']),
            'place'           => $sectionId . ':' . (int) $article['group_id'],
            'tags'            => Articles::tagIdsOf($db, (int) $article['article_id']),
            'teaser'          => (string) $article['teaser'],
            'content'         => (string) $article['content'],
            'block2'          => (string) $article['block2'],
        ]);

        // Section and group in one choice; other sections only where the user may administer
        $places = [];
        foreach ($access->targets() as $target) {
            $options = [['value' => $target['section_id'] . ':0', 'label' => self::t('NO_GROUP')]];
            foreach (Groups::list($db, $target['section_id']) as $group) {
                $options[] = ['value' => $target['section_id'] . ':' . (int) $group['group_id'], 'label' => (string) $group['title']];
            }
            $places[] = ['label' => $target['label'], 'current' => $target['section_id'] === $sectionId, 'options' => $options];
        }

        $editor = static fn (string $name, string $content, int $height): string => class_exists('WysiwygEditor')
            ? \WysiwygEditor::render("articles_{$name}_$sectionId", $content, ['name' => $name, 'height' => $height])
            : '<textarea name="' . $name . '" id="articles_' . $name . '_' . $sectionId . '" rows="8">' . htmlspecialchars($content) . '</textarea>';

        $images = [];
        if ($article !== null) {
            foreach ($db->fetchAll('SELECT `image_id`, `file`, `caption` FROM `{TP}mod_articles_images` WHERE `article_id` = ? ORDER BY `position`, `image_id`', [$articleId]) as $image) {
                $images[] = [
                    'id' => (int) $image['image_id'], 'file' => (string) $image['file'], 'caption' => (string) $image['caption'],
                    'url' => Frontend::mediaUrl("/items/$articleId/thumb/" . $image['file']),
                ];
            }
        }

        return self::twig()->render('modify_article.twig', $vars + [
            'form'       => $form,
            'exists'     => $article !== null,
            'places'     => $places,
            'tags'       => array_map(static fn (array $t): array => ['id' => (int) $t['tag_id'], 'title' => (string) $t['title'], 'style' => Tags::styleAttr($t)], Tags::forSection($db, $sectionId)),
            'use_block2' => (int) $settings['use_block2'] === 1,
            'editor_teaser'  => $editor('teaser', (string) $form['teaser'], 200),
            'editor_content' => $editor('content', (string) $form['content'], 400),
            'editor_block2'  => (int) $settings['use_block2'] === 1 ? $editor('block2', (string) $form['block2'], 200) : '',
            'preview'    => $article !== null ? self::imageInfo("/items/$articleId", (string) $article['image']) : null,
            'images'     => $images,
            'max_upload' => (int) $settings['max_upload'],
            'max_upload_mb' => self::mb((int) $settings['max_upload']),
            'url_view'   => $article !== null && is_file(AccessFiles::path((string) $article['link'])) ? Frontend::pageUrl((string) $article['link']) : '',
            'link_prefix' => '/' . AccessFiles::dir($db, $sectionId, $vars['page_id']) . '/',
            'link_suffix' => PAGE_SPACER . ($article === null ? '…' : $articleId),
        ]);
    }

    private static function settings(\Database $db, Access $access, array $vars): string
    {
        $sectionId = $vars['section_id'];
        $settings = Settings::get($db, $sectionId);
        // MB in the form, bytes in the table
        $form = $vars['old'] ?? ['max_upload' => (int) ceil((int) $settings['max_upload'] / 1048576)] + $settings;
        $choices = [];
        foreach ($db->fetchAll(
            "SELECT i.`article_id`, i.`title`, i.`image` FROM `{TP}mod_articles_items` i WHERE i.`section_id` = ? AND i.`image` <> '' ORDER BY " . Articles::orderSql((int) $settings['sort_order']),
            [$sectionId]
        ) as $a) {
            $choices[] = ['id' => (int) $a['article_id'], 'label' => $a['title'] . ': ' . $a['image']];
        }
        $templates = [
            'tpl_list_header', 'tpl_list_item', 'tpl_list_footer', 'tpl_detail_header',
            'tpl_detail_content', 'tpl_detail_footer', 'tpl_image_loop', 'tpl_block2',
        ];
        // syntax highlighting from the core module CodeMirror_Config, plain textareas without it
        if ($form['expert_mode'] && class_exists('CodeEditor')) {
            foreach ($templates as $name) {
                \CodeEditor::init("art-$name-$sectionId", 'html', ['height' => 'auto', 'toolbar' => false]);
            }
        }
        return self::twig()->render('modify_settings.twig', $vars + [
            'form'      => $form,
            'default_image' => self::imageInfo("/sections/$sectionId", (string) $settings['default_image']),
            'image_choices' => $choices,
            'max_upload_mb' => self::mb((int) $settings['max_upload']),
            'views'     => Settings::views(),
            'galleries' => Settings::GALLERIES,
            'orders'    => array_keys(Articles::ORDER),
            'is_admin'  => $access->isAdmin(),
            'dir_default' => AccessFiles::dir($db, 0, $vars['page_id']),
            'upload_limit' => (int) floor(ini_parse_quantity((string) ini_get('upload_max_filesize')) / 1048576),
            'templates' => $templates,
        ]);
    }

    /** Preview URL, file name and size of the original; null without an image. */
    private static function imageInfo(string $folder, string $file): ?array
    {
        if ($file === '') {
            return null;
        }
        $size = @getimagesize(\ARTICLES_MEDIA_PATH . "$folder/$file");
        return [
            'file' => $file,
            'url'  => Frontend::mediaUrl("$folder/preview/$file"),
            'size' => $size === false ? '' : $size[0] . ' × ' . $size[1],
        ];
    }

    private static function mb(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, ',', ''), '0'), ',');
    }

    private static function date(int $timestamp): string
    {
        return gmdate(DATE_FORMAT, $timestamp + (int) TIMEZONE);
    }
}
