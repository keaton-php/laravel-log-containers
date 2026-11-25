<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Tests\Fixtures\HookedLog;
use Keaton\LogContainers\Tests\Fixtures\PrivateHookLog;
use Keaton\LogContainers\Tests\Fixtures\ProtectedHookLog;
use Keaton\LogContainers\Tests\Fixtures\Recorder;
use Keaton\LogContainers\Tests\Fixtures\RenamedHookLog;
use Keaton\LogContainers\Tests\TestCase;
use LogicException;
use RuntimeException;

class HookTest extends TestCase
{
    public function test_a_before_hook_can_change_the_entry(): void
    {
        $entry = log_container(HookedLog::class)->info('hi', ['a' => 1]);

        $this->assertSame(['a' => 1, 'hooked' => true], $entry->context);
    }

    public function test_a_before_level_hook_can_veto_the_entry(): void
    {
        $entry = log_container(HookedLog::class)->debug('should be dropped');

        $this->assertFalse($entry->exists);
        $this->assertDatabaseCount('log_entries', 0);
    }

    public function test_an_after_hook_is_resolved_through_the_container(): void
    {
        $this->app->singleton(Recorder::class);

        $entry = log_container(HookedLog::class)->info('hi');

        $this->assertSame([$entry->id], app(Recorder::class)->ids);
    }

    public function test_a_vetoed_entry_does_not_run_the_after_hook(): void
    {
        $this->app->singleton(Recorder::class);

        log_container(HookedLog::class)->debug('dropped');

        $this->assertSame([], app(Recorder::class)->ids);
        $this->assertSame(0, LogEntry::query()->count());
    }

    public function test_a_protected_hook_is_refused_and_nothing_is_stored(): void
    {
        try {
            log_container(ProtectedHookLog::class)->info('hi');

            $this->fail('A protected hook was not refused.');
        } catch (LogicException $e) {
            $this->assertSame('Hook ['.ProtectedHookLog::class.'::beforeLog] must be public. Hooks are called through the service container.', $e->getMessage());
        }

        $this->assertDatabaseCount('log_entries', 0);
    }

    public function test_a_private_after_hook_is_refused_before_the_entry_is_stored(): void
    {
        try {
            PrivateHookLog::error('hi');

            $this->fail('A private hook was not refused.');
        } catch (LogicException $e) {
            $this->assertStringContainsString(PrivateHookLog::class.'::onError] must be public', $e->getMessage());
        }

        $this->assertDatabaseCount('log_entries', 0);
    }

    public function test_a_hidden_hook_only_matters_for_its_own_level(): void
    {
        $entry = PrivateHookLog::info('hi');

        $this->assertTrue($entry->exists);
    }

    public function test_a_hidden_hook_does_not_replace_the_exception_a_doing_block_threw(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('the block failed');

        ProtectedHookLog::doing('import', function (): void {
            throw new RuntimeException('the block failed');
        });
    }

    public function test_an_after_hook_gets_the_stored_entry_whatever_its_parameter_is_called(): void
    {
        $this->app->singleton(Recorder::class);

        $entry = RenamedHookLog::info('hi');

        $this->assertTrue($entry->exists);
        $this->assertSame([$entry->id], app(Recorder::class)->ids);
    }
}
