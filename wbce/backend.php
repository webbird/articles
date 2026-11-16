<?php
/**
 * articles – backend.php
 * The one entry for all backend forms of a section. Every call: page rights
 * (modules/admin.php), FTAN, section belongs to the page and is articles, then
 * the action checks its objects with Articles\Access. Answers with a redirect (PRG).
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

// SCRIPT_FILENAME keeps the junction path during development; __DIR__ would resolve it
require dirname($_SERVER['SCRIPT_FILENAME'], 3) . '/config.php';

// PHP drops a POST above post_max_size completely, FTAN and ids included. Back to
// the section with the reason instead of a security error; nothing is written.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST === []
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > ini_parse_quantity((string) ini_get('post_max_size'))) {
    require_once __DIR__ . '/bootstrap.php';
    // The reason goes in the URL: with display_errors on, PHP has already printed its
    // warning about the limit, so neither a session (flash) nor a Location header works.
    $to = Articles\Backend::url((int) ($_GET['page_id'] ?? 0), (int) ($_GET['section_id'] ?? 0), ['art_error' => 'post_too_large']);
    headers_sent() ? print('<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($to) . '">') : header('Location: ' . $to);
    exit;
}

$admin_header = false;
require WB_PATH . '/modules/admin.php';
require_once __DIR__ . '/bootstrap.php';

use Articles\Access;
use Articles\AccessFiles;
use Articles\Articles;
use Articles\Backend;
use Articles\Frontend;
use Articles\Groups;
use Articles\Images;
use Articles\Import;
use Articles\Settings;
use Articles\Tags;

$sectionId = (int) $section_id;
$pageId = (int) $page_id;

// admin.php checks the page permissions, but not that the section belongs to that page
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$admin->checkFTAN() || (int) $section['page_id'] !== $pageId || $section['module'] !== 'articles') {
    $admin->print_header();
    $admin->print_error($MESSAGE['GENERIC_SECURITY_ACCESS'], ADMIN_URL . '/pages/modify.php?page_id=' . $pageId);
}

$db = $database;
$access = new Access($db, $admin);
$userId = (int) $admin->get_user_id();
$post = static fn (string $name): string => is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
$ids = static fn (string $name): array => array_values(array_unique(array_filter(array_map('intval', is_array($_POST[$name] ?? null) ? $_POST[$name] : []))));

/** Back to the backend; errors keep the form input for the next request. */
$back = static function (array $params, string $success = '', array $errors = [], ?array $old = null, array $notes = []) use ($pageId, $sectionId, $admin): never {
    if ($success !== '') {
        $admin->touchSection($sectionId);
    }
    Backend::flash($sectionId, array_filter(['success' => $success, 'errors' => $errors, 'old' => $old, 'notes' => $notes]));
    header('Location: ' . Backend::url($pageId, $sectionId, $params));
    exit;
};
// The list keeps its tab and filters
$list = array_filter([
    'art_tab'    => in_array($post('art_tab'), Backend::TABS, true) ? $post('art_tab') : 'articles',
    'art_group'  => $post('art_group'),
    'art_status' => $post('art_status'),
    'art_q'      => $post('art_q'),
]);
$saved = Backend::t('SAVED');
$params = $list;

/** Runs an image step; a refused upload becomes a message, the rest of the form is saved anyway. */
$imageErrors = [];
$image = static function (callable $step, string $name = '') use (&$imageErrors): void {
    try {
        $step();
    } catch (DomainException $e) {
        $imageErrors[] = ($name !== '' ? $name . ': ' : '') . Backend::t($e->getMessage());
    }
};
$json = static function (int $status, array $data): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
};

try {
    switch ($post('art_action')) {
        case 'article_save':
            $id = (int) $post('article_id');
            $params = ['art_view' => 'edit'] + ($id > 0 ? ['art_id' => $id] : []);
            if ($id > 0 && !$access->articles([$id], $sectionId)) {
                throw new DomainException('ERR_ACCESS');
            }
            $old = [
                'article_id' => $id, 'title' => $post('title'), 'slug' => $post('slug'), 'slug_locked' => $post('slug_locked') === '1',
                'active' => $post('active') === '1', 'published_from' => $post('published_from'), 'published_until' => $post('published_until'),
                'place' => $post('place'), 'tags' => $ids('tags'), 'teaser' => $post('teaser'), 'content' => $post('content'), 'block2' => $post('block2'),
            ];
            [$target, $groupId] = array_map('intval', array_pad(explode(':', $old['place'], 2), 2, '0'));
            $from = Backend::toTimestamp($old['published_from']);
            $until = Backend::toTimestamp($old['published_until']);
            $errors = array_keys(array_filter([
                'ERR_TITLE_MISSING' => $old['title'] === '',
                'ERR_ACCESS'        => !$access->section($target) || !$access->group($groupId, $target),
                'ERR_DATE'          => $from === null || $until === null,
                'ERR_DATE_ORDER'    => $from && $until && $until <= $from,
            ]));
            if ($errors !== []) {
                $back($params, '', array_map(static fn (string $key): string => Backend::t($key), $errors), $old);
            }
            $settings = Settings::get($db, $sectionId);
            $current = $id > 0 ? $db->fetchRow('SELECT `block2` FROM `{TP}mod_articles_items` WHERE `article_id` = ?', [$id]) : null;
            $id = Articles::save($db, [
                'id' => $id, 'section_id' => $target, 'group_id' => $groupId, 'title' => $old['title'],
                'slug' => Articles::slug($old['slug'] !== '' ? $old['slug'] : $old['title']), 'active' => $old['active'],
                'published_from' => $from, 'published_until' => $until, 'teaser' => $old['teaser'], 'content' => $old['content'],
                // without Block 2 in the settings the form has no field; the stored text stays
                'block2' => (int) $settings['use_block2'] === 1 ? $old['block2'] : (string) ($current['block2'] ?? ''),
                'tags' => Tags::available($db, $target, $old['tags']),
            ], $userId);

            // Images after the article: a new article needs its id for the folder
            $targetSettings = Settings::get($db, $target);
            $sizes = Images::sizes($targetSettings);
            $max = (int) $targetSettings['max_upload'];
            $current = (string) $db->fetchValue('SELECT `image` FROM `{TP}mod_articles_items` WHERE `article_id` = ?', [$id]);
            $image(static function () use ($db, $id, $current, $post, $max, $sizes): void {
                $file = Images::replace("/items/$id", $current, Images::fileOf('image'), $post('image_remove') === '1', $max, $sizes);
                if ($file !== $current) {
                    Articles::exec($db, 'UPDATE `{TP}mod_articles_items` SET `image` = ? WHERE `article_id` = ?', [$file, $id]);
                }
            });
            Images::saveGallery($db, $id, is_array($_POST['caption'] ?? null) ? $_POST['caption'] : [], $ids('image_order'), $ids('image_delete'));
            foreach (Images::filesOf('gallery') as $file) {
                $image(static fn () => Images::addToGallery($db, $id, $file, $max, $sizes), (string) $file['name']);
            }
            [$imageId, $direction] = array_pad(explode(':', $post('image_move'), 2), 2, '');
            if ((int) $imageId > 0) {
                Images::stepGallery($db, $id, (int) $imageId, $direction === 'up');
            }

            if ($target !== $sectionId) {
                // WBCE Search re-indexes sections whose page changed; $back() touches only this one
                $admin->touchSection($target);
                $back([], Backend::t('ARTICLE_MOVED'), $imageErrors);
            }
            $back($post('save_back') === '1' && $imageErrors === [] && $post('image_move') === '' ? [] : ['art_view' => 'edit', 'art_id' => $id], $saved, $imageErrors);

        case 'gallery_upload':
            // Called by the uploader per file; answers with JSON instead of a redirect
            $id = (int) $post('article_id');
            if (!$access->articles([$id], $sectionId)) {
                $json(403, ['status' => 'error', 'message' => Backend::t('ERR_ACCESS')]);
            }
            $settings = Settings::get($db, $sectionId);
            try {
                $row = Images::addToGallery($db, $id, Images::fileOf('file') ?? [], (int) $settings['max_upload'], Images::sizes($settings));
            } catch (DomainException $e) {
                $json(400, ['status' => 'error', 'message' => Backend::t($e->getMessage())]);
            }
            $admin->touchSection($sectionId);
            $json(200, ['status' => 'ok', 'image' => [
                'id' => $row['image_id'], 'file' => $row['file'], 'url' => Frontend::mediaUrl("/items/$id/thumb/" . $row['file']),
            ]]);

        case 'article_toggle':
        case 'article_delete':
        case 'article_up':
        case 'article_down':
            $id = (int) $post('article_id');
            if (!$access->articles([$id], $sectionId)) {
                throw new DomainException('ERR_ACCESS');
            }
            match ($post('art_action')) {
                'article_toggle' => Articles::set($db, [$id], 'active', (int) $db->fetchValue('SELECT `active` FROM `{TP}mod_articles_items` WHERE `article_id` = ?', [$id]) ? 0 : 1),
                'article_delete' => Articles::delete($db, [$id]),
                default          => Articles::step($db, $sectionId, $id, $post('art_action') === 'article_up'),
            };
            $back($list, $post('art_action') === 'article_delete' ? Backend::t('DELETED') : $saved);

        case 'article_reorder':
            $order = $ids('order');
            $all = array_map('intval', array_column($db->fetchAll('SELECT `article_id` FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]), 'article_id'));
            sort($all);
            $check = $order;
            sort($check);
            if ($check !== $all) {
                throw new DomainException('ERR_ACCESS');
            }
            Articles::reorder($db, $sectionId, $order);
            $back($list, $saved);

        case 'bulk':
            $selected = $ids('ids');
            if ($selected === []) {
                throw new DomainException('ERR_NOTHING_SELECTED');
            }
            if (!$access->articles($selected, $sectionId)) {
                throw new DomainException('ERR_ACCESS');
            }
            $bulk = $post('bulk_action');
            $target = (int) $post('target');
            match ($bulk) {
                'activate', 'deactivate' => Articles::set($db, $selected, 'active', $bulk === 'activate' ? 1 : 0),
                'clear_from'             => Articles::set($db, $selected, 'published_from', 0),
                'clear_until'            => Articles::set($db, $selected, 'published_until', 0),
                'delete'                 => Articles::delete($db, $selected),
                'group'                  => $access->group((int) $post('group_id'), $sectionId)
                    ? Articles::set($db, $selected, 'group_id', (int) $post('group_id'))
                    : throw new DomainException('ERR_ACCESS'),
                'tags'                   => Articles::setTags($db, $selected, Tags::available($db, $sectionId, $ids('tag_ids'))),
                'copy', 'copy_with_tags' => $access->section($target)
                    ? Articles::copy($db, $selected, $target, $bulk === 'copy_with_tags', $userId)
                    : throw new DomainException('ERR_TARGET'),
                'move', 'move_with_tags' => $access->section($target) && $target !== $sectionId
                    ? Articles::move($db, $selected, $target, $bulk === 'move_with_tags')
                    : throw new DomainException('ERR_TARGET'),
                default                  => throw new DomainException('ERR_ACTION'),
            };
            if ((str_starts_with($bulk, 'copy') || str_starts_with($bulk, 'move')) && $target !== $sectionId) {
                $admin->touchSection($target);
            }
            $back($list, Backend::t('BULK_DONE', count($selected)));

        case 'group_save':
            $id = (int) $post('group_id');
            $old = ['group_id' => $id, 'title' => $post('title'), 'active' => $post('active') === '1'];
            if (!$access->group($id, $sectionId)) {
                throw new DomainException('ERR_ACCESS');
            }
            if ($old['title'] === '') {
                $back(['art_tab' => 'groups'] + ($id ? ['art_id' => $id] : []), '', [Backend::t('ERR_TITLE_MISSING')], $old);
            }
            $id = Groups::save($db, $sectionId, $id, $old['title'], $old['active']);
            $settings = Settings::get($db, $sectionId);
            $current = (string) $db->fetchValue('SELECT `image` FROM `{TP}mod_articles_groups` WHERE `group_id` = ?', [$id]);
            $image(static function () use ($db, $id, $current, $post, $settings): void {
                $file = Images::replace("/groups/$id", $current, Images::fileOf('image'), $post('image_remove') === '1', (int) $settings['max_upload'], Images::sizes($settings));
                if ($file !== $current) {
                    Articles::exec($db, 'UPDATE `{TP}mod_articles_groups` SET `image` = ? WHERE `group_id` = ?', [$file, $id]);
                }
            });
            $back(['art_tab' => 'groups'] + ($imageErrors === [] ? [] : ['art_id' => $id]), $saved, $imageErrors);

        case 'group_toggle':
        case 'group_delete':
        case 'group_up':
        case 'group_down':
            $id = (int) $post('group_id');
            if ($id === 0 || !$access->group($id, $sectionId)) {
                throw new DomainException('ERR_ACCESS');
            }
            match ($post('art_action')) {
                'group_toggle' => Groups::setActive($db, $id, !(int) $db->fetchValue('SELECT `active` FROM `{TP}mod_articles_groups` WHERE `group_id` = ?', [$id])),
                'group_delete' => Groups::delete($db, $id),
                default        => Groups::step($db, $sectionId, $id, $post('art_action') === 'group_up'),
            };
            $back(['art_tab' => 'groups'], $post('art_action') === 'group_delete' ? Backend::t('DELETED') : $saved);

        case 'tag_save':
            $id = (int) $post('tag_id');
            $old = ['tag_id' => $id, 'title' => $post('title'), 'color' => $post('color'), 'text_color' => $post('text_color'), 'global' => $post('global') === '1'];
            if ($id > 0 && (!$access->tag($id) || Tags::available($db, $sectionId, [$id]) === [])) {
                throw new DomainException('ERR_ACCESS');
            }
            try {
                Tags::save($db, $sectionId, $id, $old['title'], $old['color'], $old['text_color'], $access->isAdmin() && $old['global']);
            } catch (DomainException $e) {
                $back(['art_tab' => 'tags'] + ($id ? ['art_id' => $id] : []), '', [Backend::t($e->getMessage())], $old);
            }
            $back(['art_tab' => 'tags'], $saved);

        case 'tag_delete':
            $id = (int) $post('tag_id');
            if (!$access->tag($id) || Tags::available($db, $sectionId, [$id]) === []) {
                throw new DomainException('ERR_ACCESS');
            }
            Tags::delete($db, $id);
            $back(['art_tab' => 'tags'], Backend::t('DELETED'));

        case 'settings_save':
            $params = ['art_view' => 'settings'];
            if (!$access->settings($sectionId)) {
                throw new DomainException('ERR_ACCESS');
            }
            $before = Settings::get($db, $sectionId);
            Settings::save($db, $sectionId, array_filter($_POST, 'is_string'), $access->isAdmin());
            $settings = Settings::get($db, $sectionId);
            $sizes = Images::sizes($settings);

            // Standard image: an upload wins over taking one from an article, then removing
            $current = (string) $settings['default_image'];
            $fromArticle = (int) $post('default_image_from');
            $image(static function () use ($db, $sectionId, $current, $post, $settings, $sizes, $fromArticle): void {
                $folder = "/sections/$sectionId";
                $upload = Images::fileOf('default_image');
                if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE && $fromArticle > 0) {
                    $source = (string) $db->fetchValue(
                        "SELECT `image` FROM `{TP}mod_articles_items` WHERE `article_id` = ? AND `section_id` = ? AND `image` <> ''",
                        [$fromArticle, $sectionId]
                    );
                    $source !== '' || throw new DomainException('ERR_ACCESS');
                    // a copy, so deleting the article does not take the standard image along
                    $file = Images::store(ARTICLES_MEDIA_PATH . "/items/$fromArticle/$source", $source, $folder, Images::PREVIEW, $sizes);
                    Images::remove($folder, $current, Images::PREVIEW);
                } else {
                    $file = Images::replace($folder, $current, $upload, $post('default_image_remove') === '1', (int) $settings['max_upload'], $sizes);
                }
                if ($file !== $current) {
                    Articles::exec($db, 'UPDATE `{TP}mod_articles_settings` SET `default_image` = ? WHERE `section_id` = ?', [$file, $sectionId]);
                }
            });

            $notes = array_keys(array_filter([
                'NOTE_SIZES_CHANGED' => Images::sizes($before) !== $sizes,
                'NOTE_DIR_CHANGED'   => $before['articles_dir'] !== $settings['articles_dir'],
            ]));
            $back($post('save_back') === '1' && $imageErrors === [] ? [] : $params, $saved, $imageErrors, null,
                array_map(static fn (string $key): string => Backend::t($key), $notes));

        case 'access_files':
            foreach ($db->fetchAll('SELECT `article_id` FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]) as $row) {
                AccessFiles::sync($db, (int) $row['article_id']);
            }
            $back(['art_tab' => 'maintenance'], Backend::t('ACCESS_FILES_DONE'));

        case 'images_rebuild':
            [$done, $failed] = Images::rebuild($db, $sectionId);
            $back(['art_tab' => 'maintenance'], Backend::t('IMAGES_REBUILT', $done), [], null,
                array_map(static fn (string $path): string => Backend::t('IMAGE_FAILED', $path), $failed));

        case 'articles_relink':
            $back(['art_tab' => 'maintenance'], Backend::t('RELINK_DONE', Articles::relink($db, $sectionId)));

        case 'import':
        case 'demo':
            $params = ['art_tab' => 'maintenance'];
            if ($post('art_action') === 'demo') {
                $report = Import::demo($db, $post('pack'), $sectionId, $userId);
            } else {
                // "module:section"; the user must administer the source's page as well
                [$module, $source] = array_pad(explode(':', $post('source'), 2), 2, '0');
                $source = (int) $source;
                $sourcePage = (int) $db->fetchValue('SELECT `page_id` FROM `{TP}sections` WHERE `section_id` = ? AND `module` = ?', [$source, $module]);
                if ($sourcePage === 0 || !$admin->get_page_permission($sourcePage, 'admin')) {
                    throw new DomainException('ERR_ACCESS');
                }
                $report = match ($module) {
                    'news_img' => Import::fromNewsImg($db, $source, $sectionId, $pageId),
                    'articles' => Import::fromArticles($db, $source, $sectionId, $pageId),
                    default    => throw new DomainException('ERR_ACCESS'),
                };
            }
            $back([], Backend::t($post('art_action') === 'demo' ? 'DEMO_DONE' : 'IMPORT_DONE', ...array_values($report['counts'])), [], null,
                array_map(static fn (array $note): string => Backend::t($note[0], $note[1]), $report['notes']));

        default:
            throw new DomainException('ERR_ACTION');
    }
} catch (DomainException $e) {
    $back($params, '', [Backend::t($e->getMessage())]);
}
