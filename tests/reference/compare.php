<?php
/**
 * Compares an imported articles section with the news_img reference
 * (docs/Konzept.md, section 10). Fetches the same views from the articles
 * section, cuts out the module output and Block 2, normalises both sides and
 * writes them as one tag per line, so `diff` shows only real differences.
 *
 * Usage: php compare.php <wbce-dir> <reference-dir> <target_section> [<out-dir>]
 * The target must have been imported from the reference's section (mod_articles_import).
 *
 * Normalised away, because they differ by design:
 * - class prefix mod_nwi_ → mod_articles_, URLs of page, articles, groups and RSS, page title in the feed
 * - image folders (.news_img/… → .articles/…/<size>/), file names stay
 * - entities and whitespace between tags
 */

declare(strict_types=1);

if ($argc < 4 || !is_file($argv[1] . '/config.php') || !is_file($argv[2] . '/index.json') || !ctype_digit($argv[3])) {
    fwrite(STDERR, "Usage: php compare.php <wbce-dir> <reference-dir> <target_section> [<out-dir>]\n");
    exit(1);
}
$refDir = realpath($argv[2]);
$target = (int) $argv[3];
$outDir = $argv[4] ?? sys_get_temp_dir() . '/articles-compare';
chdir($argv[1]);
$_SERVER += ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1'];
require './config.php';

$index = json_decode((string) file_get_contents("$refDir/index.json"), true, 512, JSON_THROW_ON_ERROR);
$source = (int) $index['section_id'];
$pageOf = static fn (int $section): array => $database->fetchRow(
    'SELECT p.`page_id`, p.`link`, p.`page_title` FROM `{TP}sections` s JOIN `{TP}pages` p ON p.`page_id` = s.`page_id` WHERE s.`section_id` = ?',
    [$section]
) ?? exit("Section $section not found.\n");
$srcPage = $pageOf($source);
$tgtPage = $pageOf($target);
$pages = WB_URL . PAGES_DIRECTORY;

// Old post id => new link, old group id => new group id (by title)
$links = [];
foreach ($database->fetchAll(
    'SELECT im.`source_id`, i.`article_id`, i.`link` FROM `{TP}mod_articles_import` im
       JOIN `{TP}mod_articles_items` i ON i.`article_id` = im.`article_id`
      WHERE im.`source_module` = ? AND im.`source_section_id` = ? AND i.`section_id` = ?',
    ['news_img', $source, $target]
) as $row) {
    $links[(int) $row['source_id']] = [(int) $row['article_id'], $row['link']];
}
$links !== [] || exit("Section $target has no articles imported from section $source.\n");
$groups = [];
$newGroups = array_column($database->fetchAll('SELECT `group_id`, `title` FROM `{TP}mod_articles_groups` WHERE `section_id` = ?', [$target]), 'group_id', 'title');
foreach ($database->fetchAll('SELECT `group_id`, `title` FROM `{TP}mod_news_img_groups` WHERE `section_id` = ?', [$source]) as $g) {
    $groups[(int) $g['group_id']] = (int) ($newGroups[$g['title']] ?? 0);
}

/** Source URLs and ids inside a text => the articles counterparts. */
$translate = static function (string $text) use ($links, $groups, $pages, $srcPage, $tgtPage, $source, $target): string {
    $text = preg_replace_callback(
        '#' . preg_quote($pages, '#') . '/posts/[a-z0-9_-]+-(\d+)' . preg_quote(PAGE_EXTENSION, '#') . '#',
        static fn (array $m): string => isset($links[(int) $m[1]]) ? $pages . $links[(int) $m[1]][1] . PAGE_EXTENSION : $m[0],
        $text
    );
    $text = preg_replace_callback('/mod_nwi_tag_(\d+)_/', static fn (array $m): string => 'mod_articles_tag_' . ($links[(int) $m[1]][0] ?? $m[1]) . '_', $text);
    $text = preg_replace_callback('/\b(g|group_id)=(\d+)/', static fn (array $m): string => $m[1] . '=' . ($groups[(int) $m[2]] ?? $m[2]), $text);
    return strtr($text, [
        $pages . $srcPage['link'] . PAGE_EXTENSION                         => $pages . $tgtPage['link'] . PAGE_EXTENSION,
        "modules/news_img/rss.php?page_id={$srcPage['page_id']}&section_id=$source" => "modules/articles/rss.php?page_id={$tgtPage['page_id']}&section_id=$target",
        'mod_nwi_'                                                          => 'mod_articles_',
        '<title>' . $srcPage['page_title'] . '</title>'                     => '<title>' . $tgtPage['page_title'] . '</title>',
    ]);
};

/** The parts the module produces: its output in the main block and Block 2. */
$extract = static function (string $html, bool $xml): string {
    if ($xml) {
        return $html;
    }
    $parts = [];
    if (preg_match('#<div class="mod_(?:nwi|articles)_[a-z0-9_-]+">.*?(?=<a class="section_anchor"|</article>)#s', $html, $m)) {
        $parts[] = $m[0];
    }
    if (preg_match('#<aside[^>]*>(.*?)</aside>#s', $html, $m)) {
        $parts[] = "<!-- block2 -->" . $m[1];
    }
    return implode("\n", $parts);
};

$normalise = static function (string $text): string {
    $text = (string) preg_replace('#/media/\.news_img/(?:\d+/)?(?:thumb/)?#', '/media/IMG/', $text);
    $text = (string) preg_replace('#/media/\.articles/(?:items|groups|sections)/\d+/(?:preview|thumb|large)/#', '/media/IMG/', $text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = (string) preg_replace('/\s+/', ' ', $text);
    $text = (string) preg_replace('/>\s+|\s+</', '>', str_replace(' <', '<', $text));
    return trim(str_replace('<', "\n<", $text)) . "\n";
};

is_dir($outDir) || mkdir($outDir, 0777, true);
$equal = 0;
foreach ($index['urls'] as $entry) {
    $url = $translate($entry['url']);
    $xml = str_ends_with($entry['file'], '.xml');
    // cURL as in capture.php: $http_response_header is deprecated in PHP 8.5
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20]);
    $live = (string) curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

    $ref = $normalise($translate($extract((string) file_get_contents("$refDir/{$entry['file']}"), $xml)));
    $new = $normalise($extract($live, $xml));
    file_put_contents("$outDir/{$entry['name']}.ref", $ref);
    file_put_contents("$outDir/{$entry['name']}.new", $new);

    $same = $ref === $new && $status === (int) $entry['status'];
    $equal += (int) $same;
    printf("%-44s %s  %d/%d\n", $entry['name'], $same ? 'gleich ' : 'ANDERS ', $entry['status'], $status);
}
printf("%d von %d gleich, Dateien in %s\n", $equal, count($index['urls']), $outDir);
