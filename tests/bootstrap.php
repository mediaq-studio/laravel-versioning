<?php

/**
 * Test bootstrap: uses the host application autoloader plus a small autoloader
 * for this package's own test namespace, so the package tests can run without
 * their own vendor directory.
 */

$autoloaders = [
    __DIR__ . '/../vendor/autoload.php',        // standalone checkout of this package
    __DIR__ . '/../../../vendor/autoload.php',  // package installed inside a host application
];

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;
        break;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MediaQ\\Versioning\\Tests\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
