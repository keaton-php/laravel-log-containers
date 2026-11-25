<?php

declare(strict_types=1);

namespace Keaton\LogContainers;

use Illuminate\Support\ServiceProvider;
use Keaton\LogContainers\Console\PruneLogContainersCommand;
use Keaton\LogContainers\Watches\WatchRegistry;
use Keaton\LogContainers\Writing\OpenBlocks;

class LogContainersServiceProvider extends ServiceProvider
{
    public const MIGRATION = '2026_11_06_000000_create_log_containers_tables.php';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/log-containers.php', 'log-containers');

        $this->app->singleton(ExecutionContext::class);
        $this->app->singleton(WatchRegistry::class);
        $this->app->singleton(OpenBlocks::class);
        $this->app->singleton(LogContainerManager::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([PruneLogContainersCommand::class]);

        $this->publishes([
            __DIR__.'/../config/log-containers.php' => $this->app->configPath('log-containers.php'),
        ], 'log-containers-config');

        $migration = [
            __DIR__.'/../database/migrations/'.self::MIGRATION => $this->app->databasePath('migrations/'.self::MIGRATION),
        ];

        // publishesMigrations() re-dates the file on publish.
        $this->publishesMigrations($migration, 'log-containers-migrations');
    }
}
