<?php
/**
 * The droplets must not show articles of pages that are not public, nor of
 * sections outside their publication period. Links via [articles:NN] resolve on
 * public, hidden, registered and private pages, not in the trash, on pages
 * without visibility or outside the period. Changes the visibility of the page
 * and the period of the section for a moment and restores both, also on errors.
 *
 * Usage: php droplet_visibility.php <wbce-dir> <page_id> <section_id>
 * The section must be an articles section with visible articles and preview images.
 */

declare(strict_types=1);

if ($argc !== 4 || !is_file($argv[1] . '/config.php') || !ctype_digit($argv[2] . $argv[3])) {
    fwrite(STDERR, "Usage: php droplet_visibility.php <wbce-dir> <page_id> <section_id>\n");
    exit(1);
}
chdir($argv[1]);
$_SERVER += ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1'];
require './config.php';
require_once WB_PATH . '/modules/articles/bootstrap.php';
// the errorlogger of WBCE swallows CLI errors
restore_error_handler();
restore_exception_handler();

[$pageId, $sectionId] = [(int) $argv[2], (int) $argv[3]];
$db = $database;
$failed = 0;
$ok = static function (bool $cond, string $what) use (&$failed): void {
    $failed += (int) !$cond;
    echo ($cond ? 'ok   ' : 'FAIL ') . $what . "\n";
};
$count = static fn (): array => [
    'list'   => substr_count(Articles\Api::list($db, ['group_id_type' => 'section_id', 'group_id' => (string) $sectionId, 'max_news_items' => '999', 'aslist' => '1']), '<li'),
    'slider' => substr_count(Articles\Api::slider($db, $sectionId), 'carousel-item'),
];

$visibility = (string) $db->fetchValue('SELECT `visibility` FROM `{TP}pages` WHERE `page_id` = ?', [$pageId]);
$period = $db->fetchRow('SELECT `publ_start`, `publ_end` FROM `{TP}sections` WHERE `section_id` = ? AND `page_id` = ?', [$sectionId, $pageId]);
if ($period === null || !in_array($visibility, ['public', 'hidden'], true)) {
    fwrite(STDERR, "Section $sectionId is not on page $pageId, or the page is not public.\n");
    exit(1);
}
class_exists('LinkResolver') || require_once WB_PATH . '/framework/LinkResolver.php';
require_once WB_PATH . '/modules/articles/LinkResolver.php';
$resolver = new ArticlesLinkResolver();
$articleId = (int) $db->fetchValue(
    'SELECT i.`article_id` FROM `{TP}mod_articles_items` i WHERE i.`section_id` = ? AND ' . Articles\Articles::visibleSql('i') . ' ORDER BY i.`article_id` LIMIT 1',
    [$sectionId]
);
$linked = static fn (): bool => $resolver->resolve($articleId) !== null;

$setVisibility = static fn (string $v) => $db->query('UPDATE `{TP}pages` SET `visibility` = ? WHERE `page_id` = ?', [$v, $pageId]);
$setPeriod = static fn (int $start, int $end) => $db->query('UPDATE `{TP}sections` SET `publ_start` = ?, `publ_end` = ? WHERE `section_id` = ?', [$start, $end, $sectionId]);

try {
    $setPeriod(0, 0);
    $setVisibility('public');
    $public = $count();
    $ok($public['list'] > 0 && $public['slider'] > 0, 'public page: list and slider show articles ' . json_encode($public));
    $ok($articleId > 0 && $linked(), "public page: [articles:$articleId] resolves");
    // visibility => [droplets show articles, [articles:NN] resolves]
    foreach (['hidden' => [true, true], 'registered' => [false, true], 'private' => [false, true], 'none' => [false, false], 'deleted' => [false, false]] as $v => [$shown, $link]) {
        $setVisibility($v);
        $c = $count();
        $ok($shown ? $c === $public : $c === ['list' => 0, 'slider' => 0], "visibility $v: " . ($shown ? 'shown' : 'nothing') . ' ' . json_encode($c));
        $ok($linked() === $link, "visibility $v: link " . ($link ? 'resolves' : 'does not resolve'));
    }
    $setVisibility('public');
    $setPeriod(0, time() - 60);
    $ok($count() === ['list' => 0, 'slider' => 0] && !$linked(), 'section after its end: nothing, link does not resolve');
    $setPeriod(time() + 3600, 0);
    $ok($count() === ['list' => 0, 'slider' => 0] && !$linked(), 'section before its start: nothing, link does not resolve');
} finally {
    $setVisibility($visibility);
    $setPeriod((int) $period['publ_start'], (int) $period['publ_end']);
}
$ok((string) $db->fetchValue('SELECT `visibility` FROM `{TP}pages` WHERE `page_id` = ?', [$pageId]) === $visibility
    && $db->fetchRow('SELECT `publ_start`, `publ_end` FROM `{TP}sections` WHERE `section_id` = ?', [$sectionId]) == $period, 'page and section restored');
exit($failed > 0 ? 1 : 0);
