<?php

namespace MediaQ\Versioning;

use Illuminate\Support\ServiceProvider;
use MediaQ\Versioning\Console\VersionCommand;

class VersioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/versioning.php', 'versioning');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([VersionCommand::class]);

        $this->publishes([
            __DIR__ . '/../config/versioning.php' => config_path('versioning.php'),
        ], 'versioning-config');
    }
}
