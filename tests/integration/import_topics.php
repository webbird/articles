<?php
/**
 * Import from Topics against a WBCE test installation. The target is section
 * id 999997, which has no sections row, so no page, section or access file is
 * created; everything is removed again with Articles::deleteSection(), also on
 * errors. For the gallery the script puts two pictures into the folder
 * topic<id>/ of the first topic and removes them afterwards; it stops if that
 * folder already exists.
 *
 * Usage: php import_topics.php <wbce-dir> <topics_section>
 * The source must be a Topics section with at least one topic with a picture.
 */

declare(strict_types=1);

if ($argc !== 3 || !is_file($argv[1] . '/config.php') || !ctype_digit($argv[2])) {
    fwrite(STDERR, "Usage: php import_topics.php <wbce-dir> <topics_section>\n");
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
use Articles\Import;
use Articles\Settings;

$source = (int) $argv[2];
$target = 999997;
$db = $database;
$failed = 0;
$ok = static function (bool $cond, string $what) use (&$failed): void {
    $failed += (int) !$cond;
    echo ($cond ? 'ok   ' : 'FAIL ') . $what . "\n";
};
$count = static fn (string $sql, array $params = []): int => (int) $db->fetchValue($sql, $params);

$module = (string) $db->fetchValue('SELECT `module` FROM `{TP}sections` WHERE `section_id` = ?', [$source]);
$settings = $db->fetchRow("SELECT * FROM `{TP}mod_{$module}_settings` WHERE `section_id` = ?", [$source]);
$topics = $db->fetchAll("SELECT * FROM `{TP}mod_$module` WHERE `section_id` = ? ORDER BY `position`, `topic_id`", [$source]);
$first = $topics[0] ?? null;
$pictureDir = WB_PATH . $settings['picture_dir'];
$gallery = $first === null ? '' : $pictureDir . '/topic' . (int) $first['topic_id'];
$created = false;

try {
    if ($first === null || $first['picture'] === '' || is_dir($gallery)) {
        throw new RuntimeException('source needs topics with a picture and no folder topic<id>/ on the first one, stopping');
    }
    if (Settings::get($db, $target) !== null || $count('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$target])) {
        throw new RuntimeException("section $target is in use, stopping");
    }
    Settings::create($db, $target);
    // two additional pictures, the second also as larger zoom version; names in reverse natural order
    mkdir("$gallery/zoom", 0755, true);
    $created = true;
    copy("$pictureDir/{$first['picture']}", "$gallery/b-10.jpg");
    copy("$pictureDir/{$first['picture']}", "$gallery/b-9.jpg");
    copy(is_file("$pictureDir/zoom/{$first['picture']}") ? "$pictureDir/zoom/{$first['picture']}" : "$pictureDir/{$first['picture']}", "$gallery/zoom/b-9.jpg");

    echo "-- checks\n";
    $ok(Import::isTopics($db, $module) && !Import::isTopics($db, 'news_img') && !Import::isTopics($db, 'articles') && !Import::isTopics($db, '../x'), "isTopics: $module yes, others no");
    try {
        Import::fromTopics($db, (int) $db->fetchValue("SELECT `section_id` FROM `{TP}sections` WHERE `module` = 'articles' LIMIT 1"), $target, 8);
        $ok(false, 'refuses an articles section as source');
    } catch (DomainException $e) {
        $ok($e->getMessage() === 'IMPORT_SOURCE_INVALID', 'refuses an articles section as source');
    }

    echo "-- import from $module section $source\n";
    $report = Import::fromTopics($db, $source, $target, 8);
    $new = $db->fetchAll('SELECT * FROM `{TP}mod_articles_items` WHERE `section_id` = ? ORDER BY `article_id`', [$target]);
    $previews = count(array_filter($topics, static fn (array $t): bool => $t['picture'] !== ''));
    $ok(count($new) === count($topics) && $report['counts']['articles'] === count($topics) && $report['counts']['images'] === $previews + 2,
        'all topics, previews and 2 gallery images: ' . json_encode($report['counts']) . ' notes ' . json_encode($report['notes']));
    $same = true;
    foreach ($topics as $i => $t) {
        $a = $new[$i];
        $same = $same && $a['title'] === $t['title'] && $a['teaser'] === $t['content_short'] && $a['content'] === $t['content_long']
            && $a['block2'] === $t['content_extra'] && (int) $a['active'] === ((int) $t['active'] >= 4 ? 1 : 0)
            && (int) $a['published_from'] === (int) $t['published_when'] && (int) $a['published_until'] === (int) $t['published_until']
            && (int) $a['created_at'] === (int) $t['posted_first'] && (int) $a['created_by'] === (int) $t['posted_by'];
    }
    $ok($same, 'title, texts, status, dates and author as in the source, same order');
    $slug = Articles::slugOf($new[0]);
    $ok($first['link'] !== 'topic' . $first['topic_id'] || $slug === Articles::slug($first['title']), "default link topicNN replaced by the title: $slug");
    $a = $new[0];
    $original = ARTICLES_MEDIA_PATH . "/items/{$a['article_id']}/{$a['image']}";
    $ok($a['image'] !== '' && is_file($original) && is_file(ARTICLES_MEDIA_PATH . "/items/{$a['article_id']}/preview/{$a['image']}"), "preview image with derived size: {$a['image']}");
    if (is_file("$pictureDir/zoom/{$first['picture']}")) {
        $ok(getimagesize($original)[0] === min(2560, getimagesize("$pictureDir/zoom/{$first['picture']}")[0]), 'original taken from zoom/');
    }
    $images = $db->fetchAll('SELECT `file` FROM `{TP}mod_articles_images` WHERE `article_id` = ? ORDER BY `position`', [$a['article_id']]);
    $ok(array_column($images, 'file') === ['b-9.jpg', 'b-10.jpg'], 'gallery in natural order: ' . implode(', ', array_column($images, 'file')));
    $files = true;
    foreach ($images as $img) {
        foreach (['', '/thumb', '/large'] as $sub) {
            $files = $files && is_file(ARTICLES_MEDIA_PATH . "/items/{$a['article_id']}$sub/{$img['file']}");
        }
    }
    $ok($files, 'gallery files with thumb and large');
    $t = Settings::get($db, $target);
    $sort = (int) $settings['sort_topics'];
    $ok((int) $t['sort_order'] === ($sort === 1 ? 1 : 0) && (int) $t['per_page'] === (int) $settings['topics_per_page'], "sort order and page size: {$t['sort_order']}, {$t['per_page']}");
    $ok($count("SELECT COUNT(*) FROM `{TP}mod_articles_import` WHERE `source_module` = 'topics' AND `source_section_id` = ?", [$source]) === count($topics), 'origin recorded');
    try {
        Import::fromTopics($db, $source, $target, 8);
        $ok(false, 'refuses a target that is not empty');
    } catch (DomainException $e) {
        $ok($e->getMessage() === 'IMPORT_TARGET_NOT_EMPTY', 'refuses a target that is not empty');
    }
    $ok($db->fetchAll("SELECT * FROM `{TP}mod_$module` WHERE `section_id` = ? ORDER BY `position`, `topic_id`", [$source]) === $topics, 'source unchanged');
} finally {
    Articles::deleteSection($db, $target);
    if ($created) {
        array_map('unlink', glob("$gallery/zoom/*") ?: []);
        array_map('unlink', glob("$gallery/*.jpg") ?: []);
        rmdir("$gallery/zoom");
        rmdir($gallery);
    }
}
$ok($count('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$target]) === 0 && Settings::get($db, $target) === null
    && $count('SELECT COUNT(*) FROM `{TP}mod_articles_import` WHERE `source_module` = ?', ['topics']) === 0 && !is_dir($gallery), 'test section and pictures removed');
exit($failed > 0 ? 1 : 0);
