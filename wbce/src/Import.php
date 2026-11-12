<?php
/**
 * articles – Import
 * Fills an empty articles section from another section; the source stays
 * untouched. Phase 2: news_img 5.0.x and v6.0 (docs/Konzept.md, section 6).
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
        if (!in_array($gallery, Settings::GALLERIES, true)) {
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
        if ((int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$targetSectionId]) > 0
            || (int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_groups` WHERE `section_id` = ?', [$targetSectionId]) > 0) {
            throw new \DomainException('IMPORT_TARGET_NOT_EMPTY');
        }

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
}
