<?php
/**
 * Runs the news_img import from the command line, until the backend has its
 * button (Phase 4). Boots WBCE from the given installation.
 *
 * Usage: php import_news_img.php <wbce-dir> <source_section> <target_section> <target_page>
 * The target must be an empty articles section; its settings row is created if missing.
 */

declare(strict_types=1);

if ($argc !== 5 || !is_file($argv[1] . '/config.php') || !ctype_digit($argv[2] . $argv[3] . $argv[4])) {
    fwrite(STDERR, "Usage: php import_news_img.php <wbce-dir> <source_section> <target_section> <target_page>\n");
    exit(1);
}
chdir($argv[1]);
$_SERVER += ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1'];
require './config.php';
require_once WB_PATH . '/modules/articles/bootstrap.php';

[$source, $target, $page] = [(int) $argv[2], (int) $argv[3], (int) $argv[4]];
if (!$database->fetchValue('SELECT COUNT(*) FROM `{TP}mod_articles_settings` WHERE `section_id` = ?', [$target])) {
    Articles\Settings::create($database, $target);
}
try {
    $report = Articles\Import::fromNewsImg($database, $source, $target, $page);
} catch (DomainException $e) {
    fwrite(STDERR, Lang::get('MOD_ARTICLES', $e->getMessage(), $e->getMessage()) . "\n");
    exit(1);
}
foreach ($report['counts'] as $what => $count) {
    printf("%-9s %d\n", $what, $count);
}
foreach ($report['notes'] as [$key, $arg]) {
    echo '- ', sprintf(Lang::get('MOD_ARTICLES', $key, $key), $arg), "\n";
}
