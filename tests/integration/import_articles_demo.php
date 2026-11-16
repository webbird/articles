<?php
/**
 * Import from another articles section and the demo packs, against a WBCE test
 * installation. Targets are section ids without a sections row (999999 and
 * 999998), so no page or section is created; everything is removed again with
 * Articles::deleteSection(), also on errors. No access files are written for them.
 *
 * Usage: php import_articles_demo.php <wbce-dir> <source_section>
 * The source must be an articles section with articles, groups and images.
 */

declare(strict_types=1);

if ($argc !== 3 || !is_file($argv[1] . '/config.php') || !ctype_digit($argv[2])) {
    fwrite(STDERR, "Usage: php import_articles_demo.php <wbce-dir> <source_section>\n");
    exit(1);
}
chdir($argv[1]);
$_SERVER += ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1'];
require './config.php';
require_once WB_PATH . '/modules/articles/bootstrap.php';
// the errorlogger of WBCE swallows CLI errors
restore_error_handler();
restore_exception_handler();

use Articles\Articles;
use Articles\Images;
use Articles\Import;
use Articles\Settings;

$source = (int) $argv[2];
[$target, $demoTarget] = [999999, 999998];
$db = $database;
$failed = 0;
$ok = static function (bool $cond, string $what) use (&$failed): void {
    $failed += (int) !$cond;
    echo ($cond ? 'ok   ' : 'FAIL ') . $what . "\n";
};
$count = static fn (string $sql, array $params = []): int => (int) $db->fetchValue($sql, $params);
$tagsBefore = $count('SELECT COUNT(*) FROM `{TP}mod_articles_tags`');

try {
    foreach ([$target, $demoTarget] as $sid) {
        if (Settings::get($db, $sid) !== null || $count('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sid])) {
            throw new RuntimeException("section $sid is in use, stopping");
        }
        Settings::create($db, $sid);
    }

    echo "-- import from articles section $source\n";
    try {
        Import::fromArticles($db, $source, $source, 7);
        $ok(false, 'refuses itself as source');
    } catch (DomainException $e) {
        $ok($e->getMessage() === 'IMPORT_SOURCE_INVALID', 'refuses itself as source');
    }
    $report = Import::fromArticles($db, $source, $target, 7);
    $src = $db->fetchAll('SELECT * FROM `{TP}mod_articles_items` WHERE `section_id` = ? ORDER BY `position`, `article_id`', [$source]);
    $new = $db->fetchAll('SELECT * FROM `{TP}mod_articles_items` WHERE `section_id` = ? ORDER BY `position`, `article_id`', [$target]);
    $ok($report['counts']['articles'] === count($src) && count($new) === count($src), 'all articles: ' . json_encode($report['counts']));
    $fields = ['title', 'active', 'position', 'teaser', 'content', 'block2', 'published_from', 'published_until', 'created_at', 'created_by', 'image'];
    $same = array_map(static fn (array $a): array => array_intersect_key($a, array_flip($fields)), $src) === array_map(static fn (array $a): array => array_intersect_key($a, array_flip($fields)), $new);
    $ok($same, 'fields, order and dates as in the source');
    $ok(array_map(static fn (array $a): string => Articles::slugOf($a), $src) === array_map(static fn (array $a): string => Articles::slugOf($a), $new), 'slugs kept, links with new ids');
    $srcGroups = array_column($db->fetchAll('SELECT `title`, `active`, `image` FROM `{TP}mod_articles_groups` WHERE `section_id` = ? ORDER BY `position`', [$source]), null);
    $newGroups = $db->fetchAll('SELECT `title`, `active`, `image` FROM `{TP}mod_articles_groups` WHERE `section_id` = ? ORDER BY `position`', [$target]);
    $ok($srcGroups === $newGroups, count($newGroups) . ' groups with active state and image');
    $groupTitles = static fn (int $sid): array => array_map(
        static fn (array $r): string => $r['title'] . '=' . ($r['g'] ?? '-'),
        $db->fetchAll('SELECT i.`title`, g.`title` AS `g` FROM `{TP}mod_articles_items` i LEFT JOIN `{TP}mod_articles_groups` g ON g.`group_id` = i.`group_id` WHERE i.`section_id` = ? ORDER BY i.`position`, i.`article_id`', [$sid])
    );
    $ok($groupTitles($source) === $groupTitles($target), 'articles keep their group');
    $tagTitles = static fn (int $sid): array => array_map(
        static fn (array $r): string => $r['title'] . ':' . $r['tags'],
        $db->fetchAll("SELECT i.`title`, (SELECT GROUP_CONCAT(t.`title` ORDER BY t.`title`) FROM `{TP}mod_articles_item_tags` it JOIN `{TP}mod_articles_tags` t ON t.`tag_id` = it.`tag_id` WHERE it.`article_id` = i.`article_id`) AS `tags`
                         FROM `{TP}mod_articles_items` i WHERE i.`section_id` = ? ORDER BY i.`position`, i.`article_id`", [$sid])
    );
    $ok($tagTitles($source) === $tagTitles($target), 'tags by title as in the source');
    $ok($count('SELECT COUNT(*) FROM `{TP}mod_articles_tag_sections` WHERE `section_id` = ?', [$target]) === $count('SELECT COUNT(*) FROM `{TP}mod_articles_tag_sections` WHERE `section_id` = ?', [$source]), 'local tags created in the target, global ones reused');
    $ok($count('SELECT COUNT(*) FROM `{TP}mod_articles_images` m JOIN `{TP}mod_articles_items` i ON i.`article_id` = m.`article_id` WHERE i.`section_id` = ?', [$target]) === $report['counts']['images'], $report['counts']['images'] . ' gallery images');
    $missing = 0;
    foreach (Images::ofSection($db, $target) as [$folder, $file]) {
        $missing += (int) !is_file(ARTICLES_MEDIA_PATH . "$folder/$file");
    }
    $ok($missing === 0, 'all image files copied (' . count(Images::ofSection($db, $target)) . ')');
    $s = Settings::get($db, $source);
    $t = Settings::get($db, $target);
    $ok(array_diff_key($s, ['section_id' => 0, 'articles_dir' => 0]) == array_diff_key($t, ['section_id' => 0, 'articles_dir' => 0]) && $t['articles_dir'] === '', 'settings taken over, directory left empty');
    $ok($count("SELECT COUNT(*) FROM `{TP}mod_articles_import` WHERE `source_module` = 'articles' AND `source_section_id` = ?", [$source]) === count($src), 'origin recorded');
    try {
        Import::fromArticles($db, $source, $target, 7);
        $ok(false, 'refuses a target that is not empty');
    } catch (DomainException $e) {
        $ok($e->getMessage() === 'IMPORT_TARGET_NOT_EMPTY', 'refuses a target that is not empty');
    }
    $ok($count('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$source]) === count($src), 'source unchanged');

    echo "-- demo packs\n";
    $packs = Import::demoPacks();
    $ok(isset($packs['restaurant'], $packs['stadtverwaltung']), 'packs listed: ' . implode(', ', array_keys($packs)));
    try {
        Import::demo($db, '../../config', $demoTarget, 0);
        $ok(false, 'refuses an unknown pack');
    } catch (DomainException $e) {
        $ok($e->getMessage() === 'IMPORT_SOURCE_INVALID', 'refuses an unknown pack');
    }
    $report = Import::demo($db, 'restaurant', $demoTarget, 1);
    $ok($report['counts']['articles'] === 10 && $report['counts']['groups'] === 3 && $report['counts']['images'] === 13, 'restaurant: ' . json_encode($report['counts']) . ' notes ' . json_encode($report['notes']));
    $first = $db->fetchRow('SELECT * FROM `{TP}mod_articles_items` WHERE `section_id` = ? ORDER BY `position` DESC LIMIT 1', [$demoTarget]);
    $ok($first['title'] === 'Frühlingskarte ist da', 'first article of the pack on top: ' . $first['title']);
    $ok(str_ends_with((string) $first['link'], '/fruehlingskarte' . PAGE_SPACER . $first['article_id']) && $first['image'] !== '', 'slug from the pack, preview image set');
    $ds = Settings::get($db, $demoTarget);
    $ok((int) $ds['thumb_width'] === 200 && (int) $ds['preview_width'] === 300 && (int) $ds['large_width'] === 900, 'allowed settings set, others kept');
    [$w, $h] = getimagesize(ARTICLES_MEDIA_PATH . "/items/{$first['article_id']}/preview/{$first['image']}");
    $ok($w <= 300 && $h <= 300, "preview derived in the pack's size ($w x $h)");
    $ok($count('SELECT COUNT(*) FROM `{TP}mod_articles_tag_sections` WHERE `section_id` = ?', [$demoTarget]) + 0 >= 3, 'local tags created (global ones of the same title reused)');
} finally {
    foreach ([$target, $demoTarget] as $sid) {
        Articles::deleteSection($db, $sid);
    }
}
$ok($count('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` IN (999999, 999998)') === 0
    && Settings::get($db, $target) === null && !is_dir(ARTICLES_MEDIA_PATH . '/sections/999999')
    && $count('SELECT COUNT(*) FROM `{TP}mod_articles_tags`') === $tagsBefore, 'test sections removed, tags as before');
exit($failed > 0 ? 1 : 0);
