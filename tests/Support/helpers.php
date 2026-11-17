<?php

/**
 * The `version` command relies on two helpers that ship with laravel/framework
 * (not with illuminate/support). They are stubbed here so the package tests can
 * run without installing the full framework.
 */

if (! function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = \Illuminate\Container\Container::getInstance()->make('config');

        return $key === null ? $config : $config->get($key, $default);
    }
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return \Illuminate\Container\Container::getInstance()->basePath($path);
    }
}
