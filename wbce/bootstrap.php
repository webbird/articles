<?php
/**
 * articles – bootstrap.php
 * Autoloader for Articles\ in src/, language files.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

defined('ARTICLES_MEDIA_PATH') or define('ARTICLES_MEDIA_PATH', WB_PATH . MEDIA_DIRECTORY . '/.articles');

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Articles\\')) {
        $file = __DIR__ . '/src/' . strtr(substr($class, 9), '\\', '/') . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

Lang::loadLanguage(__DIR__);
