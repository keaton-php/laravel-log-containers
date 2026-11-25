<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests;

use Keaton\LogContainers\LogContainersServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LogContainersServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // The package's own tables.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Demo/fixture tables the examples and tests tag against. These are not
        // shipped (tests/ is export-ignored); they only back the test models.
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
