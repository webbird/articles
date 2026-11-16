<?php
/**
 * Rights of the backend (Articles\Access) with foreign ids, and the write paths
 * save, copy, move and delete. Runs against a WBCE test installation with a
 * stand-in for the logged-in user. Creates only rows in the articles tables (no
 * pages or sections) and removes them again, also on errors.
 *
 * Usage: php access_rights.php <wbce-dir> <page_id> <section_id> [<move_target_section>]
 * The section must be an articles section with at least one article and one group.
 * Moving is tested only with a second articles section on the same page.
 */

declare(strict_types=1);

if (!in_array($argc, [4, 5], true) || !is_file($argv[1] . '/config.php') || !ctype_digit($argv[2] . $argv[3] . ($argv[4] ?? ''))) {
    fwrite(STDERR, "Usage: php access_rights.php <wbce-dir> <page_id> <section_id> [<move_target_section>]\n");
    exit(1);
}
chdir($argv[1]);
$_SERVER += ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1'];
require './config.php';
require_once WB_PATH . '/modules/articles/bootstrap.php';
// the errorlogger of WBCE swallows CLI errors
restore_error_handler();
restore_exception_handler();

use Articles\Access;
use Articles\AccessFiles;
use Articles\Articles;
use Articles\Tags;

[$pageId, $sectionId, $moveTarget] = [(int) $argv[2], (int) $argv[3], (int) ($argv[4] ?? 0)];
$db = $database;
$failed = 0;
$ok = static function (bool $cond, string $what) use (&$failed): void {
    $failed += (int) !$cond;
    echo ($cond ? 'ok   ' : 'FAIL ') . $what . "\n";
};

/** Stand-in for the logged-in user: may administer $pages, is admin or not. */
$user = static fn (array $pages, bool $isAdmin): \Admin => new class ($pages, $isAdmin) extends \Admin {
    public function __construct(private array $grantedPages, private bool $grantedAdmin)
    {
    }
    public function get_page_permission(int|array $page, string $action = 'admin'): bool
    {
        return in_array((int) $page, $this->grantedPages, true);
    }
    public function isAdmin(): bool
    {
        return $this->grantedAdmin;
    }
};

if ((string) $db->fetchValue('SELECT `module` FROM `{TP}sections` WHERE `section_id` = ? AND `page_id` = ?', [$sectionId, $pageId]) !== 'articles') {
    fwrite(STDERR, "Section $sectionId is not an articles section on page $pageId.\n");
    exit(1);
}
$ownArticle = (int) $db->fetchValue('SELECT MIN(`article_id`) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]);
$ownGroup = (int) $db->fetchValue('SELECT MIN(`group_id`) FROM `{TP}mod_articles_groups` WHERE `section_id` = ?', [$sectionId]);
// Another section that is not ours: rows placed there must count as foreign
$otherSection = (int) $db->fetchValue("SELECT MIN(`section_id`) FROM `{TP}sections` WHERE `section_id` <> ? AND `module` <> 'articles'", [$sectionId]);
$sameePageOther = (int) $db->fetchValue("SELECT MIN(`section_id`) FROM `{TP}sections` WHERE `page_id` = ? AND `module` <> 'articles'", [$pageId]);
if ($ownArticle === 0 || $ownGroup === 0 || $otherSection === 0) {
    fwrite(STDERR, "Needs an article and a group in section $sectionId and one other section.\n");
    exit(1);
}
if ($moveTarget !== 0 && ($moveTarget === $sectionId || (string) $db->fetchValue('SELECT `module` FROM `{TP}sections` WHERE `section_id` = ? AND `page_id` = ?', [$moveTarget, $pageId]) !== 'articles')) {
    fwrite(STDERR, "Section $moveTarget is not another articles section on page $pageId.\n");
    exit(1);
}
// The articles sections of the page: what the editor of this page may target
$pageSections = array_map('intval', array_column($db->fetchAll(
    "SELECT s.`section_id` FROM `{TP}sections` s JOIN `{TP}pages` p ON p.`page_id` = s.`page_id` WHERE s.`page_id` = ? AND s.`module` = 'articles' ORDER BY p.`page_trail`, s.`position`",
    [$pageId]
), 'section_id'));

$created = ['items' => [], 'groups' => [], 'tags' => []];
$adminsOnly = (int) $db->fetchValue('SELECT `settings_admins_only` FROM `{TP}mod_articles_settings` WHERE `section_id` = ?', [$sectionId]);
try {
    // Foreign rows: an article and a group in the other section, a local tag there, a global tag
    $foreignArticle = Articles::insert($db, 'items', [
        'section_id' => $otherSection, 'group_id' => 0, 'position' => 1, 'active' => 0, 'title' => 'access test', 'link' => '', 'image' => '',
        'teaser' => '', 'content' => '', 'block2' => '', 'published_from' => 0, 'published_until' => 0,
        'created_at' => time(), 'created_by' => 0, 'modified_at' => time(), 'modified_by' => 0,
    ]);
    $created['items'][] = $foreignArticle;
    $foreignGroup = Articles::insert($db, 'groups', ['section_id' => $otherSection, 'position' => 1, 'active' => 1, 'title' => 'access test', 'image' => '']);
    $created['groups'][] = $foreignGroup;
    $foreignTag = Articles::insert($db, 'tags', ['title' => 'access test foreign']);
    $globalTag = Articles::insert($db, 'tags', ['title' => 'access test global']);
    $ownTag = Articles::insert($db, 'tags', ['title' => 'access test own']);
    array_push($created['tags'], $foreignTag, $globalTag, $ownTag);
    foreach ([[$foreignTag, $otherSection], [$globalTag, 0], [$ownTag, $sectionId]] as [$tag, $scope]) {
        Articles::exec($db, 'INSERT INTO `{TP}mod_articles_tag_sections` (`tag_id`, `section_id`) VALUES (?, ?)', [$tag, $scope]);
    }

    echo "-- editor of page $pageId\n";
    $a = new Access($db, $user([$pageId], false));
    $ok($a->section($sectionId), 'own section');
    $ok(!$a->section($otherSection), 'foreign section');
    $ok($sameePageOther === 0 || !$a->section($sameePageOther), 'other module on the same page');
    $ok(!$a->section(0), 'global scope only for admins');
    $ok($a->articles([$ownArticle], $sectionId), 'own article');
    $ok(!$a->articles([$foreignArticle], $sectionId), 'foreign article');
    $ok(!$a->articles([$ownArticle, $foreignArticle], $sectionId), 'own and foreign article together');
    $ok(!$a->articles([$foreignArticle], $otherSection), 'foreign article in its own section');
    $ok(!$a->articles([], $sectionId) && !$a->articles([0], $sectionId), 'no ids, id 0');
    $ok($a->group($ownGroup, $sectionId) && $a->group(0, $sectionId), 'own group, no group');
    $ok(!$a->group($foreignGroup, $sectionId), 'foreign group');
    $ok($a->tag($ownTag), 'own tag');
    $ok(!$a->tag($foreignTag), 'foreign tag');
    $ok(!$a->tag($globalTag), 'global tag only for admins');
    $ok(!$a->tag(999999999), 'unknown tag');
    $ok(array_column($a->targets(), 'section_id') === $pageSections, 'targets: the articles sections of the own page (' . implode(', ', $pageSections) . ')');
    $ok($a->settings($sectionId), 'settings while open to everyone');
    Articles::exec($db, 'UPDATE `{TP}mod_articles_settings` SET `settings_admins_only` = 1 WHERE `section_id` = ?', [$sectionId]);
    $ok(!(new Access($db, $user([$pageId], false)))->settings($sectionId), 'settings for admins only: editor refused');
    $ok((new Access($db, $user([$pageId], true)))->settings($sectionId), 'settings for admins only: admin allowed');
    Articles::exec($db, 'UPDATE `{TP}mod_articles_settings` SET `settings_admins_only` = ? WHERE `section_id` = ?', [$adminsOnly, $sectionId]);

    echo "-- user without rights on page $pageId\n";
    $b = new Access($db, $user([], false));
    $ok(!$b->section($sectionId), 'section refused');
    $ok(!$b->articles([$ownArticle], $sectionId), 'article refused');
    $ok(!$b->tag($ownTag), 'tag refused');
    $ok($b->targets() === [], 'no targets');

    echo "-- admin\n";
    $c = new Access($db, $user([$pageId], true));
    $ok($c->tag($globalTag) && $c->section(0), 'global tag and scope');
    $ok(!$c->articles([$foreignArticle], $sectionId), 'foreign article stays refused');

    echo "-- save, copy, delete\n";
    $id = Articles::save($db, [
        'id' => 0, 'section_id' => $sectionId, 'group_id' => 0, 'title' => 'Access-Test', 'slug' => 'access-test', 'active' => true,
        'published_from' => 0, 'published_until' => 0, 'teaser' => '<p>t</p>', 'content' => '', 'block2' => '', 'tags' => [$ownTag],
    ], 0);
    $created['items'][] = $id;
    $link = (string) $db->fetchValue('SELECT `link` FROM `{TP}mod_articles_items` WHERE `article_id` = ?', [$id]);
    $ok(is_file(AccessFiles::path($link)), 'saved: visible article has its access file');
    $ok(Articles::tagIdsOf($db, $id) === [$ownTag], 'saved: tag');
    is_dir(ARTICLES_MEDIA_PATH . "/items/$id") || mkdir(ARTICLES_MEDIA_PATH . "/items/$id/preview", 0755, true);
    file_put_contents(ARTICLES_MEDIA_PATH . "/items/$id/preview/x.txt", 'x');

    $before = array_map('intval', array_column($db->fetchAll('SELECT `article_id` FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]), 'article_id'));
    Articles::copy($db, [$id], $sectionId, true, 0);
    $copy = (int) max(array_diff(array_map('intval', array_column($db->fetchAll('SELECT `article_id` FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]), 'article_id')), $before) ?: [0]);
    $created['items'][] = $copy;
    $copyRow = $db->fetchRow('SELECT * FROM `{TP}mod_articles_items` WHERE `article_id` = ?', [$copy]) ?? [];
    $ok($copy > 0 && (int) $copyRow['active'] === 0, 'copy: inactive');
    $ok(!is_file(AccessFiles::path((string) $copyRow['link'])), 'copy: no access file while inactive');
    $ok(is_file(ARTICLES_MEDIA_PATH . "/items/$copy/preview/x.txt"), 'copy: own image folder');
    $ok(Articles::tagIdsOf($db, $copy) === [$ownTag], 'copy: tags taken along');

    if ($moveTarget !== 0) {
        echo "-- move to section $moveTarget and back\n";
        Articles::set($db, [$id], 'group_id', $ownGroup);
        Articles::move($db, [$id], $moveTarget, true);
        $row = $db->fetchRow('SELECT * FROM `{TP}mod_articles_items` WHERE `article_id` = ?', [$id]);
        $ok((int) $row['section_id'] === $moveTarget && (int) $row['group_id'] === 0, 'moved: other section, without group');
        $ok((int) $row['position'] === (int) $db->fetchValue('SELECT MAX(`position`) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$moveTarget]), 'moved: on top of the target');
        $ok($row['link'] === AccessFiles::link(AccessFiles::dir($db, $moveTarget, $pageId), 'access-test', $id), 'moved: link in the directory of the target');
        $file = AccessFiles::path((string) $row['link']);
        $ok(is_file($file) && str_contains((string) file_get_contents($file), "define('ARTICLE_SECTION', $moveTarget)"), 'moved: access file points to the target section');
        $tags = Articles::tagIdsOf($db, $id);
        $ok(count($tags) === 1 && $tags !== [$ownTag]
            && (string) $db->fetchValue('SELECT `title` FROM `{TP}mod_articles_tags` WHERE `tag_id` = ?', [$tags[0]]) === 'access test own'
            && (int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_tag_sections` WHERE `tag_id` = ? AND `section_id` = ?', [$tags[0], $moveTarget]) === 1,
            'moved with tags: local tag recreated in the target by title');
        Articles::move($db, [$id], $sectionId, false);
        $row = $db->fetchRow('SELECT * FROM `{TP}mod_articles_items` WHERE `article_id` = ?', [$id]);
        $ok((int) $row['section_id'] === $sectionId && Articles::tagIdsOf($db, $id) === [], 'moved back without tags');
        $ok(str_contains((string) file_get_contents(AccessFiles::path((string) $row['link'])), "define('ARTICLE_SECTION', $sectionId)"), 'moved back: access file points to the own section');
        $link = (string) $row['link'];
    }

    Articles::delete($db, [$id, $copy]);
    $ok(!(int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `article_id` IN ' . Articles::in([$id, $copy])), 'delete: rows gone');
    $ok(!(int) $db->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_item_tags` WHERE `article_id` IN ' . Articles::in([$id, $copy])), 'delete: tag links gone');
    $ok(!is_file(AccessFiles::path($link)) && !is_dir(ARTICLES_MEDIA_PATH . "/items/$id") && !is_dir(ARTICLES_MEDIA_PATH . "/items/$copy"), 'delete: access file and folders gone');
} finally {
    Articles::exec($db, 'UPDATE `{TP}mod_articles_settings` SET `settings_admins_only` = ? WHERE `section_id` = ?', [$adminsOnly, $sectionId]);
    if ($created['items'] !== []) {
        Articles::delete($db, $created['items']);
    }
    foreach ($created['groups'] as $group) {
        Articles::exec($db, 'DELETE FROM `{TP}mod_articles_groups` WHERE `group_id` = ?', [$group]);
    }
    // also the tag that moving recreated in the target
    foreach (array_unique(array_merge($created['tags'], array_map('intval', array_column(
        $db->fetchAll("SELECT `tag_id` FROM `{TP}mod_articles_tags` WHERE `title` LIKE 'access test%'"),
        'tag_id'
    )))) as $tag) {
        Tags::delete($db, $tag);
    }
}
$ok(!(int) $db->fetchValue("SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `title` IN ('access test', 'Access-Test')")
    && !(int) $db->fetchValue("SELECT COUNT(*) FROM `{TP}mod_articles_tags` WHERE `title` LIKE 'access test%'"), 'test rows removed');
exit($failed > 0 ? 1 : 0);
