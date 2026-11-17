<?php
/**
 * articles – Import
 * Fills an empty articles section from another section, the source stays
 * untouched: news_img 5.0.x and v6.0, another articles section, Topics, or a
 * demo pack from demodata/ (docs/Konzept.md, section 6).
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Import
{
    /** news_img looks for group images in this order, the last match wins. */
    private const GROUP_IMAGE_TYPES = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /**
     * Maps a news_img settings row to articles settings.
     *
     * @return array{0: array<string, int|string>, 1: list<array{0: string, 1: string}>} settings, notes
     */
    public static function mapNewsImgSettings(array $s): array
    {
        $notes = [];
        $size  = static function (mixed $value): array {
            return preg_match('/^\s*(\d+)\s*x\s*(\d+)/i', (string) $value, $m) ? [(int) $m[1], (int) $m[2]] : [0, 0];
        };
        $tpl = static fn (mixed $value): string => str_replace('mod_nwi_', 'mod_articles_', (string) $value);
        $yes = static fn (mixed $value): int => strtoupper((string) $value) === 'Y' ? 1 : 0;

        $view = (string) ($s['view'] ?? 'default');
        if (!in_array($view, Settings::VIEWS, true)) {
            $notes[] = ['IMPORT_VIEW_REPLACED', $view];
            $view    = 'default';
        }
        $gallery = (string) ($s['gallery'] ?? 'fotorama');
        if ($gallery === 'lightcase') {
            // Lightcase was replaced by Tobii, also a lightbox with thumbnails
            $notes[] = ['IMPORT_GALLERY_TOBII', $gallery];
            $gallery = 'tobii';
        } elseif (!in_array($gallery, Settings::GALLERIES, true)) {
            $notes[] = ['IMPORT_GALLERY_REPLACED', $gallery];
            $gallery = 'fotorama';
        }
        [$previewWidth, $previewHeight] = $size($s['resize_preview'] ?? '');
        [$thumbWidth, $thumbHeight]     = $size($s['imgthumbsize'] ?? '');
        // news_img had one crop switch for everything; on all three the result looks the same.
        $crop = $yes($s['crop_preview'] ?? 'N');

        return [[
            'tpl_list_header'      => $tpl($s['header'] ?? ''),
            'tpl_list_item'        => $tpl($s['post_loop'] ?? ''),
            'tpl_list_footer'      => $tpl($s['footer'] ?? ''),
            'tpl_detail_header'    => $tpl($s['post_header'] ?? ''),
            'tpl_detail_content'   => $tpl($s['post_content'] ?? ''),
            'tpl_detail_footer'    => $tpl($s['post_footer'] ?? ''),
            'tpl_image_loop'       => $tpl($s['image_loop'] ?? ''),
            'tpl_block2'           => $tpl($s['block2'] ?? ''),
            'sort_order'           => (int) ($s['view_order'] ?? 0),
            'per_page'             => (int) ($s['posts_per_page'] ?? 0),
            'preview_width'        => $previewWidth,
            'preview_height'       => $previewHeight,
            'preview_crop'         => $crop,
            'thumb_width'          => $thumbWidth,
            'thumb_height'         => $thumbHeight,
            'thumb_crop'           => $crop,
            'large_width'          => (int) ($s['imgmaxwidth'] ?? 0),
            'large_height'         => (int) ($s['imgmaxheight'] ?? 0),
            'large_crop'           => $crop,
            'max_upload'           => (int) ($s['imgmaxsize'] ?? 0) ?: ini_parse_quantity((string) ini_get('upload_max_filesize')),
            'use_block2'           => $yes($s['use_second_block'] ?? 'N'),
            'view'                 => $view,
            'gallery'              => $gallery,
            'articles_dir'         => (string) preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($s['posts_dir'] ?? '')),
            'default_image'        => basename((string) ($s['default_preview_image'] ?? '')),
            'expert_mode'          => ($s['mode'] ?? '') === 'advanced' ? 1 : 0,
            'settings_admins_only' => $yes($s['show_settings_only_admins'] ?? 'N'),
        ], $notes];
    }

    /**
     * Slug of an imported post: its link without folder and ID, else its title.
     */
    public static function slug(string $link, int $postId, string $title): string
    {
        $slug = (string) preg_replace('/' . preg_quote(PAGE_SPACER, '/') . $postId . '$/', '', basename($link));
        foreach ([$slug, $title] as $candidate) {
            $candidate = page_filename($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
        return 'article';
    }

    /**
     * Imports a news_img section into an empty articles section.
     *
     * @return array{counts: array<string, int>, notes: list<array{0: string, 1: string}>}
     * @throws \DomainException with a MOD_ARTICLES text key when source or target do not fit
     */
    public static function fromNewsImg(\Database $db, int $sourceSectionId, int $targetSectionId, int $targetPageId): array
    {
        $src = static fn (string $table): string => '`{TP}mod_news_img_' . $table . '`';

        if ($db->fetchValue("SELECT `module` FROM `{TP}sections` WHERE `section_id` = ?", [$sourceSectionId]) !== 'news_img'
            || !($settings = $db->fetchRow('SELECT * FROM ' . $src('settings') . ' WHERE `section_id` = ?', [$sourceSectionId]))) {
            throw new \DomainException('IMPORT_SOURCE_INVALID');
        }
        self::assertEmpty($db, $targetSectionId);

        [$mapped, $notes] = self::mapNewsImgSettings($settings);
        $groups = $db->fetchAll('SELECT * FROM ' . $src('groups') . ' WHERE `section_id` = ? ORDER BY `position`', [$sourceSectionId]);
        $posts  = $db->fetchAll('SELECT * FROM ' . $src('posts') . ' WHERE `section_id` = ? ORDER BY `post_id`', [$sourceSectionId]);
        $postIds = array_map('intval', array_column($posts, 'post_id'));
        $in      = $postIds === [] ? '(0)' : '(' . implode(',', $postIds) . ')';
        $images  = $db->fetchAll('SELECT * FROM ' . $src('img') . " WHERE `post_id` IN $in ORDER BY `post_id`, `position`");
        $postTags = $db->fetchAll('SELECT `post_id`, `tag_id` FROM ' . $src('tags_posts') . " WHERE `post_id` IN $in");
        // Local tags of the section and every tag its posts use; the latter may be global.
        $textColor = $db->fieldExists('{TP}mod_news_img_tags', 'tag_text_color') ? 't.`tag_text_color`' : 'NULL';
        $tags = $db->fetchAll(
            "SELECT t.`tag_id`, t.`tag`, t.`tag_color`, $textColor AS `tag_text_color`,
                    (SELECT COUNT(*) FROM " . $src('tags_sections') . " g WHERE g.`tag_id` = t.`tag_id` AND g.`section_id` = 0) AS `global`
             FROM " . $src('tags') . " t
             WHERE t.`tag_id` IN (SELECT `tag_id` FROM " . $src('tags_sections') . " WHERE `section_id` = ?)
                OR t.`tag_id` IN (SELECT `tag_id` FROM " . $src('tags_posts') . " WHERE `post_id` IN $in)
             ORDER BY t.`tag_id`",
            [$sourceSectionId]
        );

        $srcMedia = WB_PATH . MEDIA_DIRECTORY . '/.news_img';
        $copies   = [];   // [from, to] pairs, run after the commit
        $now      = time();
        $counts   = ['articles' => 0, 'groups' => 0, 'tags' => 0, 'images' => 0];

        $db->transaction(function (\Database $db) use (
            $mapped, $groups, $posts, $images, $postTags, $tags, $srcMedia,
            $sourceSectionId, $targetSectionId, $targetPageId, $now, &$copies, &$counts
        ): void {
            $write = static function (bool|string $result) use ($db): void {
                // transaction() only sees the error of the last query, so check each one
                if ($result !== true || $db->hasError()) {
                    throw new \RuntimeException('articles import: ' . (is_string($result) ? $result : $db->getError()));
                }
            };
            $insert = static function (string $table, array $row) use ($db, $write): int {
                $write($db->insertRow('{TP}mod_articles_' . $table, $row));
                return (int) $db->lastInsertId();
            };

            $write($db->upsertRow('{TP}mod_articles_settings', 'section_id', ['section_id' => $targetSectionId] + $mapped));
            if ($mapped['default_image'] !== '') {
                $copies[] = [$srcMedia . '/' . $mapped['default_image'], "/sections/$targetSectionId", $mapped['default_image'], ['preview']];
            }
            $dir = AccessFiles::dir($db, $targetSectionId, $targetPageId);

            $groupMap = [];
            foreach ($groups as $group) {
                $file = '';
                foreach (self::GROUP_IMAGE_TYPES as $type) {
                    if (is_file($srcMedia . '/image' . (int) $group['group_id'] . '.' . $type)) {
                        $file = 'image' . (int) $group['group_id'] . '.' . $type;
                    }
                }
                $id = $insert('groups', [
                    'section_id' => $targetSectionId,
                    'position'   => (int) $group['position'],
                    'active'     => (int) $group['active'] ? 1 : 0,
                    'title'      => (string) $group['title'],
                    'image'      => $file,
                ]);
                $groupMap[(int) $group['group_id']] = $id;
                if ($file !== '') {
                    $copies[] = [$srcMedia . '/' . $file, "/groups/$id", $file, ['preview']];
                }
                $counts['groups']++;
            }

            $tagMap = [];
            foreach ($tags as $tag) {
                $existing = (int) $tag['global'] > 0 ? (int) $db->fetchValue(
                    'SELECT t.`tag_id` FROM `{TP}mod_articles_tags` t
                     JOIN `{TP}mod_articles_tag_sections` s ON s.`tag_id` = t.`tag_id` AND s.`section_id` = 0
                     WHERE t.`title` = ?',
                    [(string) $tag['tag']]
                ) : 0;
                if ($existing > 0) {
                    $tagMap[(int) $tag['tag_id']] = $existing;
                    continue;
                }
                $id = $insert('tags', [
                    'title'      => (string) $tag['tag'],
                    'color'      => $tag['tag_color'] ?: null,
                    'text_color' => $tag['tag_text_color'] ?: null,
                ]);
                $write($db->insertRow('{TP}mod_articles_tag_sections', [
                    'tag_id'     => $id,
                    'section_id' => (int) $tag['global'] > 0 ? 0 : $targetSectionId,
                ]));
                $tagMap[(int) $tag['tag_id']] = $id;
                $counts['tags']++;
            }

            $imagesByPost = [];
            foreach ($images as $image) {
                $imagesByPost[(int) $image['post_id']][] = $image;
            }
            foreach ($posts as $post) {
                $postId = (int) $post['post_id'];
                // CREATED_DATE in news_img is the time of the access file.
                $accessFile = WB_PATH . PAGES_DIRECTORY . $post['link'] . PAGE_EXTENSION;
                $id = $insert('items', [
                    'section_id'      => $targetSectionId,
                    'group_id'        => $groupMap[(int) $post['group_id']] ?? 0,
                    'position'        => (int) $post['position'],
                    'active'          => (int) $post['active'] ? 1 : 0,
                    'title'           => (string) $post['title'],
                    'link'            => '',
                    'image'           => '',
                    'teaser'          => (string) $post['content_short'],
                    'content'         => (string) $post['content_long'],
                    'block2'          => (string) $post['content_block2'],
                    'published_from'  => (int) $post['published_when'],
                    'published_until' => (int) $post['published_until'],
                    'created_at'      => is_file($accessFile) ? (int) filemtime($accessFile) : (int) $post['posted_when'],
                    'created_by'      => (int) $post['posted_by'],
                    'modified_at'     => (int) $post['posted_when'],
                    'modified_by'     => (int) $post['posted_by'],
                ]);

                // Preview and gallery share the article folder; a name clash gets a number.
                $taken = [];
                foreach ($imagesByPost[$postId] ?? [] as $image) {
                    $file = basename((string) $image['picname']);
                    $taken[$file] = true;
                    $write($db->insertRow('{TP}mod_articles_images', [
                        'article_id' => $id,
                        'file'       => $file,
                        'caption'    => (string) $image['picdesc'],
                        'position'   => (int) $image['position'],
                    ]));
                    $copies[] = [$srcMedia . "/$postId/$file", "/items/$id", $file, ['large']];
                    $copies[] = [$srcMedia . "/$postId/thumb/$file", "/items/$id/thumb", $file, []];
                    $counts['images']++;
                }
                $preview = basename((string) $post['image']);
                if ($preview !== '') {
                    $target = $preview;
                    for ($n = 1; isset($taken[$target]); $n++) {
                        $target = pathinfo($preview, PATHINFO_FILENAME) . "-$n." . pathinfo($preview, PATHINFO_EXTENSION);
                    }
                    $copies[] = [$srcMedia . '/' . $preview, "/items/$id", $target, ['preview']];
                    $preview = $target;
                }
                $db->query(
                    'UPDATE `{TP}mod_articles_items` SET `link` = ?, `image` = ? WHERE `article_id` = ?',
                    [AccessFiles::link($dir, self::slug((string) $post['link'], $postId, (string) $post['title']), $id), $preview, $id]
                );
                $write(true);

                foreach ($postTags as $postTag) {
                    if ((int) $postTag['post_id'] === $postId && isset($tagMap[(int) $postTag['tag_id']])) {
                        $write($db->insertRow('{TP}mod_articles_item_tags', ['article_id' => $id, 'tag_id' => $tagMap[(int) $postTag['tag_id']]]));
                    }
                }
                $write($db->insertRow('{TP}mod_articles_import', [
                    'article_id'        => $id,
                    'source_module'     => 'news_img',
                    'source_section_id' => $sourceSectionId,
                    'source_id'         => $postId,
                    'imported_at'       => $now,
                ]));
                $counts['articles']++;
            }
        });

        // Files after the commit. news_img has no originals any more, so its
        // resized files count as originals and as the derived size at once.
        foreach ($copies as [$from, $folder, $name, $derived]) {
            if (!is_file($from)) {
                $notes[] = ['IMPORT_FILE_MISSING', substr($from, strlen(WB_PATH))];
                continue;
            }
            foreach (array_merge([$folder], array_map(static fn (string $d): string => "$folder/$d", $derived)) as $to) {
                $to = \ARTICLES_MEDIA_PATH . $to;
                if (!(is_dir($to) || mkdir($to, 0755, true)) || !copy($from, "$to/$name")) {
                    $notes[] = ['IMPORT_FILE_FAILED', substr("$to/$name", strlen(WB_PATH))];
                }
            }
        }
        // The target was empty, so all its articles are new.
        foreach ($db->fetchAll('SELECT `article_id` FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$targetSectionId]) as $row) {
            AccessFiles::sync($db, (int) $row['article_id']);
        }

        return ['counts' => $counts, 'notes' => $notes];
    }

    /**
     * Imports another articles section into an empty one: settings (without
     * the directory of the access files, the links are built for the target
     * page), groups, tags, all articles with gallery, and their image folders.
     *
     * @return array{counts: array<string, int>, notes: list<array{0: string, 1: string}>}
     * @throws \DomainException with a MOD_ARTICLES text key when source or target do not fit
     */
    public static function fromArticles(\Database $db, int $sourceSectionId, int $targetSectionId, int $targetPageId): array
    {
        if ($sourceSectionId === $targetSectionId
            || $db->fetchValue('SELECT `module` FROM `{TP}sections` WHERE `section_id` = ?', [$sourceSectionId]) !== 'articles'
            || !($settings = Settings::get($db, $sourceSectionId))) {
            throw new \DomainException('IMPORT_SOURCE_INVALID');
        }
        self::assertEmpty($db, $targetSectionId);

        $now = time();
        $counts = ['articles' => 0, 'groups' => 0, 'tags' => 0, 'images' => 0];
        $folders = $settings['default_image'] !== '' ? [["/sections/$sourceSectionId", "/sections/$targetSectionId"]] : [];
        $tagsBefore = (int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_tag_sections` WHERE `section_id` = ?', [$targetSectionId]);

        $db->transaction(static function (\Database $db) use ($settings, $sourceSectionId, $targetSectionId, $targetPageId, $now, &$counts, &$folders): void {
            $result = $db->upsertRow('{TP}mod_articles_settings', 'section_id', ['section_id' => $targetSectionId, 'articles_dir' => ''] + $settings);
            if ($result !== true || $db->hasError()) {
                throw new \RuntimeException('articles import: settings: ' . (is_string($result) ? $result : $db->getError()));
            }
            $dir = AccessFiles::dir($db, $targetSectionId, $targetPageId);

            $groupMap = [];
            foreach ($db->fetchAll('SELECT * FROM `{TP}mod_articles_groups` WHERE `section_id` = ? ORDER BY `position`, `group_id`', [$sourceSectionId]) as $group) {
                $old = (int) $group['group_id'];
                $groupMap[$old] = Articles::insert($db, 'groups', ['section_id' => $targetSectionId] + array_diff_key($group, ['group_id' => 0, 'section_id' => 0]));
                if ($group['image'] !== '') {
                    $folders[] = ["/groups/$old", "/groups/{$groupMap[$old]}"];
                }
                $counts['groups']++;
            }

            foreach ($db->fetchAll('SELECT * FROM `{TP}mod_articles_items` WHERE `section_id` = ? ORDER BY `position`, `article_id`', [$sourceSectionId]) as $a) {
                $old = (int) $a['article_id'];
                $id = Articles::insert($db, 'items', [
                    'section_id' => $targetSectionId,
                    'group_id'   => $groupMap[(int) $a['group_id']] ?? 0,
                ] + array_diff_key($a, ['article_id' => 0, 'section_id' => 0, 'group_id' => 0]));
                Articles::exec($db, 'UPDATE `{TP}mod_articles_items` SET `link` = ? WHERE `article_id` = ?', [AccessFiles::link($dir, Articles::slugOf($a), $id), $id]);
                Articles::exec($db, 'INSERT INTO `{TP}mod_articles_images` (`article_id`, `file`, `caption`, `position`)'
                    . ' SELECT ?, `file`, `caption`, `position` FROM `{TP}mod_articles_images` WHERE `article_id` = ?', [$id, $old]);
                $counts['images'] += (int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_images` WHERE `article_id` = ?', [$id]);
                // Local tags of the source become tags of the target (same title), global ones stay
                foreach (Articles::mapTags($db, Articles::tagIdsOf($db, $old), $targetSectionId) as $tagId) {
                    Articles::exec($db, 'INSERT INTO `{TP}mod_articles_item_tags` (`article_id`, `tag_id`) VALUES (?, ?)', [$id, $tagId]);
                }
                Articles::insert($db, 'import', [
                    'article_id' => $id, 'source_module' => 'articles', 'source_section_id' => $sourceSectionId, 'source_id' => $old, 'imported_at' => $now,
                ]);
                $folders[] = ["/items/$old", "/items/$id"];
                $counts['articles']++;
            }
        });
        $counts['tags'] = (int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_tag_sections` WHERE `section_id` = ?', [$targetSectionId]) - $tagsBefore;

        // Files after the commit
        foreach ($folders as [$from, $to]) {
            Articles::copyDir(\ARTICLES_MEDIA_PATH . $from, \ARTICLES_MEDIA_PATH . $to);
        }
        foreach ($db->fetchAll('SELECT `article_id` FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$targetSectionId]) as $row) {
            AccessFiles::sync($db, (int) $row['article_id']);
        }
        return ['counts' => $counts, 'notes' => []];
    }

    /** Settings a demo pack may set; the values still go through Settings::save(). */
    public const DEMO_SETTINGS = [
        'sort_order', 'per_page', 'use_block2', 'view', 'gallery',
        'preview_width', 'preview_height', 'preview_crop', 'thumb_width', 'thumb_height', 'thumb_crop',
        'large_width', 'large_height', 'large_crop',
    ];

    /** @return array<string, array{name: string, description: string}> demo packs in demodata/<folder>/ */
    public static function demoPacks(): array
    {
        $packs = [];
        foreach (glob(dirname(__DIR__) . '/demodata/*/data.php') ?: [] as $file) {
            $folder = basename(dirname($file));
            $meta = json_decode((string) @file_get_contents(dirname($file) . '/meta.json'), true);
            $packs[$folder] = ['name' => (string) ($meta['name'] ?? $folder), 'description' => (string) ($meta['description'] ?? '')];
        }
        return $packs;
    }

    /**
     * Fills an empty section with a demo pack: allowed settings, groups,
     * tags (an existing tag of the same title is used, new ones are local),
     * articles with preview image and gallery. The first article of the pack ends up on top.
     * shortcut: not one transaction (Articles::save() commits per article); a failure halfway leaves a partly filled section, deleting it cleans up.
     *
     * @return array{counts: array<string, int>, notes: list<array{0: string, 1: string}>}
     */
    public static function demo(\Database $db, string $pack, int $targetSectionId, int $userId): array
    {
        // the folder name only counts if demoPacks() lists it, so no path from outside reaches include
        if (!array_key_exists($pack, self::demoPacks())) {
            throw new \DomainException('IMPORT_SOURCE_INVALID');
        }
        self::assertEmpty($db, $targetSectionId);
        $dir = dirname(__DIR__) . "/demodata/$pack";
        $data = (static fn (string $file): mixed => include $file)("$dir/data.php");
        if (!is_array($data)) {
            throw new \RuntimeException("articles demo pack $pack: data.php returns no array");
        }
        $counts = ['articles' => 0, 'groups' => 0, 'tags' => 0, 'images' => 0];
        $notes = [];

        $sizes = self::applySettings($db, $targetSectionId, array_intersect_key(is_array($data['settings'] ?? null) ? $data['settings'] : [], array_flip(self::DEMO_SETTINGS)));

        $groups = [];
        foreach ($data['groups'] ?? [] as $group) {
            $groups[(string) $group['title']] = Groups::save($db, $targetSectionId, 0, (string) $group['title'], (bool) ($group['active'] ?? true));
            $counts['groups']++;
        }

        $tags = [];
        foreach (Tags::forSection($db, $targetSectionId) as $tag) {
            $tags[mb_strtolower((string) $tag['title'])] = (int) $tag['tag_id'];
        }
        foreach ($data['tags'] ?? [] as $tag) {
            $key = mb_strtolower((string) $tag['title']);
            if (!isset($tags[$key])) {
                $tags[$key] = Tags::save($db, $targetSectionId, 0, (string) $tag['title'], (string) ($tag['color'] ?? ''), (string) ($tag['text_color'] ?? ''), false);
                $counts['tags']++;
            }
        }

        // New articles go on top, so the last one of the pack comes first
        foreach (array_reverse($data['posts'] ?? []) as $post) {
            $slug = Articles::slug((string) ($post['slug'] ?? $post['title']));
            $id = Articles::save($db, [
                'id' => 0, 'section_id' => $targetSectionId, 'group_id' => $groups[(string) ($post['group'] ?? '')] ?? 0,
                'title' => (string) $post['title'], 'slug' => $slug, 'active' => (bool) ($post['active'] ?? true),
                'published_from' => (int) ($post['published_from'] ?? 0), 'published_until' => (int) ($post['published_until'] ?? 0),
                'teaser' => (string) ($post['teaser'] ?? ''), 'content' => (string) ($post['content'] ?? ''), 'block2' => (string) ($post['block2'] ?? ''),
                'tags' => array_values(array_filter(array_map(static fn (string $t): int => $tags[mb_strtolower($t)] ?? 0, $post['tags'] ?? []))),
            ], $userId);
            $counts['articles']++;

            // Pack images go through the same check and re-encoding as uploads
            $folder = "$dir/images/" . basename((string) ($post['slug'] ?? ''));
            $store = static function (string $name, array $derived) use ($folder, $id, $sizes, $pack, &$notes, &$counts): string {
                $name = basename($name);
                try {
                    $file = is_file("$folder/$name") ? Images::store("$folder/$name", $name, "/items/$id", $derived, $sizes) : '';
                } catch (\DomainException) {
                    $file = '';
                }
                if ($file === '') {
                    $notes[] = ['IMPORT_FILE_MISSING', "demodata/$pack/images/" . basename($folder) . "/$name"];
                    return '';
                }
                $counts['images']++;
                return $file;
            };
            $preview = (string) ($post['preview_image'] ?? '') !== '' ? $store((string) $post['preview_image'], Images::PREVIEW) : '';
            if ($preview !== '') {
                Articles::exec($db, 'UPDATE `{TP}mod_articles_items` SET `image` = ? WHERE `article_id` = ?', [$preview, $id]);
            }
            foreach ($post['images'] ?? [] as $name) {
                $file = $store((string) $name, Images::GALLERY);
                if ($file !== '') {
                    Images::addFile($db, $id, $file);
                }
            }
        }

        return ['counts' => $counts, 'notes' => $notes];
    }

    /**
     * Imports a Topics section into an empty articles section: page size and
     * sort order, all topics with picture and additional pictures. The module
     * may be a renamed copy of Topics, its tables are found by the module name.
     * Not taken over: templates (they need the CSS and placeholders of Topics),
     * keywords, descriptions, groups, "see also" and comments.
     * shortcut: images are stored after the commit, a failure there leaves articles without images (noted in the report).
     *
     * @return array{counts: array<string, int>, notes: list<array{0: string, 1: string}>}
     * @throws \DomainException with a MOD_ARTICLES text key when source or target do not fit
     */
    public static function fromTopics(\Database $db, int $sourceSectionId, int $targetSectionId, int $targetPageId): array
    {
        $module = (string) $db->fetchValue('SELECT `module` FROM `{TP}sections` WHERE `section_id` = ?', [$sourceSectionId]);
        if (!self::isTopics($db, $module)
            || !($settings = $db->fetchRow("SELECT * FROM `{TP}mod_{$module}_settings` WHERE `section_id` = ?", [$sourceSectionId]))) {
            throw new \DomainException('IMPORT_SOURCE_INVALID');
        }
        self::assertEmpty($db, $targetSectionId);

        $notes = [];
        // Topics sorts by position (0) or date (1) like articles; score, title, event and comments do not exist here
        $sort = (int) $settings['sort_topics'];
        if ($sort !== 0 && $sort !== 1) {
            $notes[] = ['IMPORT_SORT_REPLACED', ''];
        }
        $sizes = self::applySettings($db, $targetSectionId, ['sort_order' => $sort === 1 ? 1 : 0, 'per_page' => max(0, (int) $settings['topics_per_page'])]);

        $topics = $db->fetchAll("SELECT * FROM `{TP}mod_$module` WHERE `section_id` = ? ORDER BY `position`, `topic_id`", [$sourceSectionId]);
        $now = time();
        $counts = ['articles' => 0, 'groups' => 0, 'tags' => 0, 'images' => 0];
        $map = [];   // article id => topic
        $db->transaction(static function (\Database $db) use ($topics, $sourceSectionId, $targetSectionId, $targetPageId, $now, &$map, &$counts): void {
            $dir = AccessFiles::dir($db, $targetSectionId, $targetPageId);
            foreach ($topics as $topic) {
                $topicId = (int) $topic['topic_id'];
                $id = Articles::insert($db, 'items', [
                    'section_id'      => $targetSectionId,
                    'group_id'        => 0,
                    'position'        => (int) $topic['position'],
                    // 4 public, 5 current, 6 top; below that unpublished, registered visitors or hidden
                    'active'          => (int) $topic['active'] >= 4 ? 1 : 0,
                    'title'           => (string) $topic['title'],
                    'link'            => '',
                    'image'           => '',
                    'teaser'          => (string) $topic['content_short'],
                    'content'         => (string) $topic['content_long'],
                    'block2'          => (string) $topic['content_extra'],
                    'published_from'  => (int) $topic['published_when'],
                    'published_until' => (int) $topic['published_until'],
                    'created_at'      => (int) $topic['posted_first'],
                    'created_by'      => (int) $topic['posted_by'],
                    'modified_at'     => (int) $topic['posted_modified'] ?: (int) $topic['posted_first'],
                    'modified_by'     => (int) $topic['posted_by'],
                ]);
                // "topic12" is the default link of Topics and says nothing, the title does
                $link = (string) $topic['link'];
                $slug = self::slug(basename($link) === "topic$topicId" ? '' : $link, $topicId, (string) $topic['title']);
                Articles::exec($db, 'UPDATE `{TP}mod_articles_items` SET `link` = ? WHERE `article_id` = ?', [AccessFiles::link($dir, $slug, $id), $id]);
                Articles::insert($db, 'import', [
                    'article_id' => $id, 'source_module' => 'topics', 'source_section_id' => $sourceSectionId, 'source_id' => $topicId, 'imported_at' => $now,
                ]);
                $map[$id] = $topic;
                $counts['articles']++;
            }
        });

        $hidden = count(array_filter($topics, static fn (array $t): bool => (int) $t['active'] >= 1 && (int) $t['active'] <= 3));
        if ($hidden > 0) {
            $notes[] = ['IMPORT_TOPICS_HIDDEN', (string) $hidden];
        }
        $comments = $db->tableExists("{TP}mod_{$module}_comments") && $topics !== []
            ? (int) $db->fetchValue("SELECT COUNT(*) FROM `{TP}mod_{$module}_comments` WHERE `topic_id` IN (" . implode(',', array_map('intval', array_column($topics, 'topic_id'))) . ')')
            : 0;
        if ($comments > 0) {
            $notes[] = ['IMPORT_COMMENTS_SKIPPED', (string) $comments];
        }

        // Images after the commit, through the same check and re-encoding as uploads; zoom/ holds the largest version
        $base = realpath(WB_PATH . '/' . ltrim((string) $settings['picture_dir'], '/'));
        $root = realpath(WB_PATH);
        if ($base === false || !str_starts_with($base, $root . DIRECTORY_SEPARATOR)) {
            $base = null;
            $notes[] = ['IMPORT_FILE_MISSING', (string) $settings['picture_dir']];
        }
        foreach ($map as $id => $topic) {
            $store = static function (string $dir, string $name, array $derived) use ($id, $sizes, $root, &$notes, &$counts): string {
                $source = is_file("$dir/zoom/$name") ? "$dir/zoom/$name" : "$dir/$name";
                try {
                    $file = is_file($source) ? Images::store($source, $name, "/items/$id", $derived, $sizes) : '';
                } catch (\DomainException) {
                    $file = '';
                }
                if ($file === '') {
                    $notes[] = ['IMPORT_FILE_MISSING', str_replace('\\', '/', substr($source, strlen($root)))];
                    return '';
                }
                $counts['images']++;
                return $file;
            };
            $picture = basename((string) $topic['picture']);
            if ($picture !== '' && $base !== null && ($file = $store($base, $picture, Images::PREVIEW)) !== '') {
                Articles::exec($db, 'UPDATE `{TP}mod_articles_items` SET `image` = ? WHERE `article_id` = ?', [$file, $id]);
            }
            foreach ($base === null ? [] : self::topicPictures($db, $module, "$base/topic" . (int) $topic['topic_id'], (int) $topic['topic_id']) as [$name, $caption]) {
                if (($file = $store("$base/topic" . (int) $topic['topic_id'], $name, Images::GALLERY)) !== '') {
                    Images::addFile($db, $id, $file, $caption);
                }
            }
            AccessFiles::sync($db, $id);
        }
        return ['counts' => $counts, 'notes' => $notes];
    }

    /** A module with the tables of Topics; copies of Topics carry their folder name in the table names. */
    public static function isTopics(\Database $db, string $module): bool
    {
        return preg_match('/^[a-z0-9_]+$/', $module) === 1 && !in_array($module, ['news_img', 'articles'], true)
            && $db->fieldExists("{TP}mod_$module", 'topic_id') && $db->tableExists("{TP}mod_{$module}_settings");
    }

    /**
     * Additional pictures of a topic like Topics shows them: from the table
     * addpics if it lists any, else every image in the folder topic<id>/.
     *
     * @return list<array{0: string, 1: string}> file name, caption
     */
    private static function topicPictures(\Database $db, string $module, string $dir, int $topicId): array
    {
        if ($db->tableExists("{TP}mod_{$module}_addpics")) {
            $rows = $db->fetchAll("SELECT `picname`, `picdesc` FROM `{TP}mod_{$module}_addpics` WHERE `topic_id` = ? ORDER BY `position`", [$topicId]);
            if ($rows !== []) {
                return array_map(static fn (array $r): array => [basename((string) $r['picname']), (string) $r['picdesc']], $rows);
            }
        }
        $files = array_values(array_filter(
            is_dir($dir) ? scandir($dir) : [],
            static fn (string $f): bool => is_file("$dir/$f") && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
        ));
        natcasesort($files);
        return array_map(static fn (string $f): array => [$f, ''], array_values($files));
    }

    /**
     * Sets some settings on top of the current ones, checked like the settings form.
     *
     * @param array<string, int|string> $values
     * @return array<string, array{0: int, 1: int, 2: bool}> the image sizes afterwards
     */
    private static function applySettings(\Database $db, int $sectionId, array $values): array
    {
        $current = Settings::get($db, $sectionId) ?? throw new \DomainException('ERR_SECTION');
        $in = ['max_upload' => (string) (int) ceil((int) $current['max_upload'] / 1048576)] + array_map('strval', $current);
        foreach ($values as $key => $value) {
            $in[$key] = (string) $value;
        }
        Settings::save($db, $sectionId, $in, false);
        return Images::sizes(Settings::get($db, $sectionId));
    }

    /** Imports only fill a section without articles and groups. */
    private static function assertEmpty(\Database $db, int $sectionId): void
    {
        if ((int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]) > 0
            || (int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_groups` WHERE `section_id` = ?', [$sectionId]) > 0) {
            throw new \DomainException('IMPORT_TARGET_NOT_EMPTY');
        }
    }
}
