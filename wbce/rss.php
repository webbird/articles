<?php
/**
 * articles – rss.php
 * Feed of a section, optionally of one group: ?page_id=&section_id=[&group_id=]
 * Delivers only if the section is an articles section of that page, the page
 * is visible to the visitor and the section is inside its publication period
 * (requirements from news_img 5.0.34). Otherwise 404.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

$pageId    = filter_input(INPUT_GET, 'page_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$sectionId = filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$groupId   = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($pageId) || !is_int($sectionId) || ($groupId !== null && !is_int($groupId))) {
    http_response_code(400);
    exit;
}

// The module is linked in via a junction during development, so __DIR__ would point into the repository.
require dirname($_SERVER['SCRIPT_FILENAME'], 3) . '/config.php';
require_once __DIR__ . '/bootstrap.php';

$notFound = static function (): never {
    http_response_code(404);
    exit;
};
$section = $database->fetchRow(
    "SELECT `publ_start`, `publ_end` FROM `{TP}sections` WHERE `section_id` = ? AND `page_id` = ? AND `module` = 'articles'",
    [$sectionId, $pageId]
) ?? $notFound();
$now = time();
if (((int) $section['publ_start'] !== 0 && $now < (int) $section['publ_start']) || ((int) $section['publ_end'] !== 0 && $now > (int) $section['publ_end'])) {
    $notFound();
}
$wb = new Frontend();
$page = $database->fetchRow('SELECT * FROM `{TP}pages` WHERE `page_id` = ?', [$pageId]) ?? $notFound();
$wb->page_is_visible($page) || $notFound();
if ($groupId !== null && !$database->fetchValue(
    'SELECT COUNT(*) FROM `{TP}mod_articles_groups` WHERE `group_id` = ? AND `section_id` = ? AND `active` = 1',
    [$groupId, $sectionId]
)) {
    $notFound();
}
$wb->page_id = $pageId;
$wb->get_page_details();
$wb->get_website_settings();

// news_img sorted the feed by posted_when, imported as modified_at
$items = $database->fetchAll(
    'SELECT i.`title`, i.`teaser`, i.`link` FROM `{TP}mod_articles_items` i WHERE i.`section_id` = ? AND '
    . Articles\Articles::visibleSql('i') . ($groupId !== null ? ' AND i.`group_id` = ' . $groupId : '')
    . ' ORDER BY i.`modified_at` DESC, i.`article_id` DESC',
    [$sectionId]
);

$cdata = static fn (string $text): string => '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $text) . ']]>';
$xml = static fn (string $text): string => htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$charset = defined('DEFAULT_CHARSET') ? DEFAULT_CHARSET : 'utf-8';

header('Content-Type: text/xml; charset=' . $charset);
echo '<?xml version="1.0" encoding="' . $xml($charset) . '"?>', "\n";
?>
<rss version="2.0">
    <channel>
        <title><?= $xml(PAGE_TITLE) ?></title>
        <link><?= $xml(WB_URL) ?></link>
        <description><?= $xml(PAGE_DESCRIPTION) ?></description>
        <language><?= $xml(strtolower(DEFAULT_LANGUAGE)) ?></language>
        <copyright><?= $xml('Copyright ' . date('Y') . ', ' . WEBSITE_TITLE) ?></copyright>
        <category><?= $xml(WEBSITE_TITLE) ?></category>
<?php foreach ($items as $item): $url = $xml(Articles\Frontend::pageUrl((string) $item['link'])); ?>
        <item>
            <title><?= $cdata((string) $item['title']) ?></title>
            <description><?= $cdata(LinkResolver::resolveContent((string) $item['teaser'])) ?></description>
            <guid><?= $url ?></guid>
            <link><?= $url ?></link>
        </item>
<?php endforeach; ?>
    </channel>
</rss>
