<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Keaton\LogContainers\ExecutionContext;
use Keaton\LogContainers\Facades\LogContainers;
use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\LogContainerManager;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use Keaton\LogContainers\Watches\WatchRegistry;

class ServiceProviderTest extends TestCase
{
    public function test_the_core_services_are_singletons(): void
    {
        $this->assertSame(app(ExecutionContext::class), app(ExecutionContext::class));
        $this->assertSame(app(WatchRegistry::class), app(WatchRegistry::class));
        $this->assertSame(app(LogContainerManager::class), app(LogContainerManager::class));
    }

    public function test_the_config_is_merged(): void
    {
        $this->assertTrue(config('log-containers.enabled'));
        $this->assertIsArray(config('log-containers.level_presets.problems'));
    }

    public function test_the_prune_command_is_registered(): void
    {
        $this->artisan('log-containers:prune')->assertSuccessful();
    }

    public function test_the_facade_resolves_the_manager(): void
    {
        $this->assertInstanceOf(LogContainerManager::class, LogContainers::getFacadeRoot());
    }

    public function test_the_helper_resolves_a_container(): void
    {
        $this->assertInstanceOf(LogContainer::class, log_container(WidgetLog::class));
    }

    public function test_the_package_tables_exist(): void
    {
        $this->assertDatabaseCount('log_entries', 0);
        $this->assertDatabaseCount('log_sessions', 0);
        $this->assertDatabaseCount('log_tags', 0);
    }
}
