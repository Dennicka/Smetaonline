<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Trenor\\Core\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    $path = __DIR__ . '/src/' . $relativePath;

    if (is_readable($path)) {
        require_once $path;
    }
});
