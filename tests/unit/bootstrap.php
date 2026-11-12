<?php
/**
 * Unit tests run without WBCE: only the autoloader for Articles\.
 */

declare(strict_types=1);

error_reporting(E_ALL);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Articles\\')) {
        $file = dirname(__DIR__, 2) . '/wbce/src/' . strtr(substr($class, 9), '\\', '/') . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
