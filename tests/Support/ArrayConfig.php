<?php

namespace MediaQ\Versioning\Tests\Support;

/**
 * Stand-in for Laravel's config repository, so the command can be tested
 * without pulling in laravel/framework.
 */
final class ArrayConfig
{
    /**
     * @param  array<string, mixed>  $items
     */
    public function __construct(private array $items = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }
}
