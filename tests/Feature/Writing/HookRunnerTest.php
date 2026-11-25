<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature\Writing;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Tests\Fixtures\HookedLog;
use Keaton\LogContainers\Tests\Fixtures\PrivateHookLog;
use Keaton\LogContainers\Tests\Fixtures\ProtectedHookLog;
use Keaton\LogContainers\Tests\Fixtures\Recorder;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use Keaton\LogContainers\Writing\HookRunner;
use LogicException;

class HookRunnerTest extends TestCase
{
    public function test_a_container_without_hooks_gets_its_attributes_back(): void
    {
        $attributes = ['message' => 'hi', 'context' => null];

        $this->assertSame($attributes, $this->hooks()->before(log_container(WidgetLog::class), 'info', $attributes));
    }

    public function test_a_before_hook_can_change_the_attributes(): void
    {
        $result = $this->hooks()->before(log_container(HookedLog::class), 'info', ['context' => ['a' => 1]]);

        $this->assertSame(['context' => ['a' => 1, 'hooked' => true]], $result);
    }

    public function test_a_level_hook_runs_after_the_general_one_and_can_veto(): void
    {
        $this->assertFalse($this->hooks()->before(log_container(HookedLog::class), 'debug', ['context' => null]));
    }

    public function test_an_after_hook_gets_the_entry_and_its_own_dependencies(): void
    {
        $this->app->singleton(Recorder::class);

        $entry = new LogEntry;
        $entry->id = 7;

        $this->hooks()->after(log_container(HookedLog::class), 'info', $entry);

        $this->assertSame([7], app(Recorder::class)->ids);
    }

    public function test_a_protected_hook_is_refused_by_name(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Hook ['.ProtectedHookLog::class.'::beforeLog] must be public.');

        $this->hooks()->before(log_container(ProtectedHookLog::class), 'info', []);
    }

    public function test_a_private_after_hook_is_refused_before_the_write(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Hook ['.PrivateHookLog::class.'::onError] must be public.');

        $this->hooks()->before(log_container(PrivateHookLog::class), 'error', []);
    }

    public function test_a_hidden_hook_for_another_level_is_left_alone(): void
    {
        $this->assertSame(['message' => 'hi'], $this->hooks()->before(log_container(PrivateHookLog::class), 'info', ['message' => 'hi']));
    }

    private function hooks(): HookRunner
    {
        return app(HookRunner::class);
    }
}
