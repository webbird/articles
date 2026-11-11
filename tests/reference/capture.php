<?php
/**
 * Saves the frontend output of one news_img section as reference for the
 * comparison after the articles import (docs/Konzept.md, Phase 0 and 3).
 *
 * Usage: php capture.php <wbce-dir> <section_id> <wbce-url>
 * Example: php capture.php D:/…/htdocs/wbce 12 http://localhost:4443/wbce
 *
 * Writes into tests/reference/news_img-<version>/: one HTML or XML file per
 * URL and index.json with URL, HTTP status and file name. Saves raw output;
 * normalising (tokens, times) belongs to the comparison, not to the capture.
 */

declare(strict_types=1);

if ($argc !== 4 || !is_file($argv[1] . '/config.php') || !ctype_digit($argv[2])) {
    fwrite(STDERR, "Usage: php capture.php <wbce-dir> <section_id> <wbce-url>\n");
    exit(1);
}
$wbPath    = rtrim(str_replace('\\', '/', $argv[1]), '/');
$sectionId = (int) $argv[2];
$wbUrl     = rtrim($argv[3], '/');

$config = (string) file_get_contents($wbPath . '/config.php');
$const  = static function (string $name) use ($config): string {
    preg_match("/define\\(\\s*'" . $name . "'\\s*,\\s*'([^']*)'/", $config, $m) || exit("$name not found in config.php\n");
    return $m[1];
};
[$host, $port] = array_pad(explode(':', $const('DB_HOST'), 2), 2, '3306');
$tp = $const('TABLE_PREFIX');
$db = new PDO(
    "mysql:host=$host;port=$port;dbname=" . $const('DB_NAME') . ';charset=utf8mb4',
    $const('DB_USERNAME'),
    $const('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$all = static function (string $sql, array $params = []) use ($db, $tp): array {
    $stmt = $db->prepare(str_replace('{TP}', $tp, $sql));
    $stmt->execute($params);
    return $stmt->fetchAll();
};
$setting = static fn (string $name, string $default): string
    => (string) ($all('SELECT `value` FROM `{TP}settings` WHERE `name` = ?', [$name])[0]['value'] ?? $default);

$pagesUrl  = $wbUrl . $setting('pages_directory', '/pages');
$extension = $setting('page_extension', '.php');
$version   = (string) ($all("SELECT `version` FROM `{TP}addons` WHERE `directory` = 'news_img'")[0]['version'] ?? 'unbekannt');
$page      = $all('SELECT p.`page_id`, p.`link` FROM `{TP}sections` s JOIN `{TP}pages` p ON p.`page_id` = s.`page_id` WHERE s.`section_id` = ?', [$sectionId])[0]
    ?? exit("Section $sectionId not found.\n");
$pageUrl   = $pagesUrl . $page['link'] . $extension;

// ── What to fetch ──────────────────────────────────────────────────────────
$urls = [
    'liste'            => $pageUrl,
    'liste-seite-2'    => $pageUrl . '?p=5',
    'liste-seite-3'    => $pageUrl . '?p=10',
    'liste-monat'      => $pageUrl . '?m=9&y=2026&method=1',
    'liste-ungueltig'  => $pageUrl . '?p=abc&g=-1',
];
foreach ($all('SELECT `group_id`, `title` FROM `{TP}mod_news_img_groups` WHERE `section_id` = ? ORDER BY `position`', [$sectionId]) as $g) {
    // Names from titles and slugs, not IDs: IDs change with every seed run.
    $key = strtolower($g['title']);
    $urls['gruppe-' . $key] = $pageUrl . '?g=' . $g['group_id'];
    $urls['rss-gruppe-' . $key] = $wbUrl . "/modules/news_img/rss.php?page_id={$page['page_id']}&section_id=$sectionId&group_id={$g['group_id']}";
}
foreach ($all('SELECT DISTINCT t.`tag` FROM `{TP}mod_news_img_tags` t JOIN `{TP}mod_news_img_tags_sections` s ON s.`tag_id` = t.`tag_id` WHERE s.`section_id` IN (0, ?) ORDER BY t.`tag`', [$sectionId]) as $t) {
    $urls['tag-' . strtolower($t['tag'])] = $pageUrl . '?tags=' . rawurlencode($t['tag']);
}
foreach ($all('SELECT `post_id`, `link`, `group_id` FROM `{TP}mod_news_img_posts` WHERE `section_id` = ? ORDER BY `post_id`', [$sectionId]) as $p) {
    $key = preg_replace('/[-_]\d+$/', '', basename($p['link']));
    $urls['detail-' . $key] = $pagesUrl . $p['link'] . $extension;
    if ((int) $p['group_id'] > 0) {
        $urls['detail-' . $key . '-in-gruppe'] = $pagesUrl . $p['link'] . $extension . '?g=' . $p['group_id'];
    }
}
$urls['rss'] = $wbUrl . "/modules/news_img/rss.php?page_id={$page['page_id']}&section_id=$sectionId";

// ── Fetch and save ─────────────────────────────────────────────────────────
$target = __DIR__ . '/news_img-' . preg_replace('/[^0-9A-Za-z._-]/', '', $version);
is_dir($target) || mkdir($target, 0755, true);
$index = [];
foreach ($urls as $name => $url) {
    // cURL instead of file_get_contents(): $http_response_header is deprecated in PHP 8.5
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20]);
    $body   = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $file   = $name . (str_starts_with($name, 'rss') ? '.xml' : '.html');
    file_put_contents("$target/$file", (string) $body);
    $index[] = ['name' => $name, 'url' => $url, 'status' => $status, 'file' => $file, 'bytes' => strlen((string) $body)];
    printf("%3d %7d  %s\n", $status, strlen((string) $body), $name);
}
file_put_contents(
    "$target/index.json",
    json_encode(['news_img' => $version, 'section_id' => $sectionId, 'captured' => gmdate('c'), 'urls' => $index], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);
echo "Gespeichert in $target\n";
