<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature\Writing;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogSession;
use Keaton\LogContainers\Tests\Fixtures\SecretLog;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use Keaton\LogContainers\Writing\SessionRecorder;

class SessionRecorderTest extends TestCase
{
    public function test_a_session_opens_with_its_name_and_prepared_context(): void
    {
        $session = $this->sessions()->open(log_container(SecretLog::class), ['password' => 'hunter2'], 'login', ['ip' => '203.0.113.9']);

        $this->assertSame('secrets', $session->container_key);
        $this->assertSame('login', $session->name);
        $this->assertSame(LogSession::STATUS_OPEN, $session->status);
        $this->assertSame(['password' => '[redacted]', 'ip' => '203.0.113.9'], $session->context);
    }

    public function test_a_session_stores_the_parent_it_is_given(): void
    {
        $parent = $this->sessions()->open(log_container(WidgetLog::class), [], 'import');
        $child = $this->sessions()->openIfEnabled(log_container(WidgetLog::class), [], 'one file', $parent->id);

        $this->assertNull($parent->parent_id);
        $this->assertSame($parent->id, $child?->parent_id);
    }

    public function test_no_session_is_opened_for_a_container_that_is_off(): void
    {
        config()->set('log-containers.containers', [WidgetLog::class => ['enabled' => false]]);

        $this->assertNull($this->sessions()->openIfEnabled(log_container(WidgetLog::class), [], 'import'));
        $this->assertDatabaseCount('log_sessions', 0);
    }

    public function test_entries_are_counted_and_error_levels_are_counted_twice(): void
    {
        $session = $this->sessions()->open(log_container(WidgetLog::class), [], 'import');

        foreach (['info', 'error', 'exception', 'success'] as $level) {
            $entry = new LogEntry;
            $entry->session_id = $session->id;
            $entry->level = $level;

            $this->sessions()->count($entry);
        }

        $session->refresh();

        $this->assertSame(4, $session->entry_count);
        $this->assertSame(2, $session->error_count);
    }

    public function test_an_entry_outside_a_session_counts_against_nothing(): void
    {
        $session = $this->sessions()->open(log_container(WidgetLog::class), [], 'import');

        $entry = new LogEntry;
        $entry->level = 'error';

        $this->sessions()->count($entry);

        $this->assertSame(0, (int) $session->refresh()->entry_count);
    }

    public function test_closing_records_the_status_and_the_duration(): void
    {
        $completed = $this->sessions()->open(log_container(WidgetLog::class), [], 'import');
        $failed = $this->sessions()->open(log_container(WidgetLog::class), [], 'import');

        $this->sessions()->close($completed, microtime(true) - 0.25, false);
        $this->sessions()->close($failed, microtime(true), true);

        $this->assertSame(LogSession::STATUS_COMPLETED, $completed->refresh()->status);
        $this->assertGreaterThanOrEqual(250, $completed->duration_ms);
        $this->assertNotNull($completed->ended_at);
        $this->assertSame(LogSession::STATUS_FAILED, $failed->refresh()->status);
    }

    private function sessions(): SessionRecorder
    {
        return app(SessionRecorder::class);
    }
}
