<?php

/**
 * Test bootstrap: loads the package autoloader (own vendor/ first, then a host
 * application) plus a small autoloader for this package's own test namespace.
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

// Stubs for the laravel/framework helpers the console command uses.
require __DIR__ . '/Support/helpers.php';

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
