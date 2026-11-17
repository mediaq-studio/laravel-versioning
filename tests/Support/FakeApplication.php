<?php

namespace MediaQ\Versioning\Tests\Support;

use Illuminate\Container\Container;

/**
 * Minimal application container that answers the framework calls the console
 * command makes (`base_path()`, `config()`, `runningUnitTests()`), so the
 * command can be exercised without booting a full Laravel application.
 */
final class FakeApplication extends Container
{
    public function __construct(private readonly string $base, public readonly ArrayConfig $config)
    {
        $this->instance('config', $config);
    }

    public function basePath(string $path = ''): string
    {
        return $this->base . ($path === '' ? '' : '/' . $path);
    }

    public function runningUnitTests(): bool
    {
        return false;
    }

    public function runningInConsole(): bool
    {
        return true;
    }
}
