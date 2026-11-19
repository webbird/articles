<?php
/**
 * Builds the installation package for WBCE: dist/articles-<version>.zip with the
 * contents of wbce/ at the top level, info.php as first entry (the WBCE installer
 * takes the folder of the first info.php it finds as the module root).
 * Leaves out dotfiles (.gitignore, .DS_Store, …); everything else in wbce/ is packed.
 *
 * Usage: php build.php [<output-dir>]
 * Needs the zip extension (the PHP of the Portable has it).
 */

declare(strict_types=1);

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The PHP extension zip is missing.\n");
    exit(1);
}
$source = __DIR__ . '/wbce';
if (!preg_match("/module_version\\s*=\\s*'([^']+)'/", (string) file_get_contents("$source/info.php"), $m)) {
    fwrite(STDERR, "No \$module_version in wbce/info.php.\n");
    exit(1);
}
$outDir = rtrim($argv[1] ?? __DIR__ . '/dist', '/\\');
is_dir($outDir) || mkdir($outDir, 0755, true);
$target = "$outDir/articles-{$m[1]}.zip";
is_file($target) && unlink($target);

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $name = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
    // dotfiles and dot folders anywhere in the path
    if (!preg_match('~(^|/)\.~', $name)) {
        $files[$name] = $file->getPathname();
    }
}
ksort($files);
$files = ['info.php' => $files['info.php']] + $files;

$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create $target\n");
    exit(1);
}
foreach ($files as $name => $path) {
    $zip->addFile($path, $name);
}
$zip->close();

printf("%s: %d Dateien, %s KB\n", $target, count($files), number_format(filesize($target) / 1024, 0, ',', '.'));
