<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature\Writing;

use Keaton\LogContainers\Models\LogSession;
use Keaton\LogContainers\Tests\Fixtures\HookedLog;
use Keaton\LogContainers\Tests\Fixtures\ProblemsLog;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use Keaton\LogContainers\Writing\EntryWriter;
use Keaton\LogContainers\Writing\Scope;
use RuntimeException;

class EntryWriterTest extends TestCase
{
    public function test_an_entry_carries_the_scope_it_was_written_in(): void
    {
        $session = LogSession::query()->create([
            'container_key' => 'widgets',
            'status' => LogSession::STATUS_OPEN,
            'started_at' => now(),
        ]);

        $scope = new Scope(
            type: 'batch',
            doing: 'importing',
            during: 'row 7',
            sessionId: $session->id,
            context: ['request' => 'r-1'],
            tags: [['type' => null, 'id' => null, 'label' => 'nightly']],
        );

        $entry = $this->writer()->write(log_container(WidgetLog::class), $scope, 'warning', 'Skipped', ['row' => 7]);

        $this->assertTrue($entry->exists);
        $this->assertSame('widgets', $entry->container_key);
        $this->assertSame('warning', $entry->level);
        $this->assertSame('batch', $entry->type);
        $this->assertSame('importing', $entry->doing);
        $this->assertSame('row 7', $entry->during);
        $this->assertSame($session->id, $entry->session_id);
        $this->assertSame(['source' => 'widget-log', 'request' => 'r-1', 'row' => 7], $entry->context);
        $this->assertSame(['nightly'], $entry->tags()->pluck('label')->all());
        $this->assertSame(1, $session->refresh()->entry_count);
    }

    public function test_an_empty_scope_writes_a_bare_entry(): void
    {
        $entry = $this->writer()->write(log_container(ProblemsLog::class), new Scope, 'error', 'Failed');

        $this->assertTrue($entry->exists);
        $this->assertNull($entry->doing);
        $this->assertNull($entry->session_id);
        $this->assertNull($entry->context);
        $this->assertDatabaseCount('log_tags', 0);
    }

    public function test_an_exception_is_stored_with_the_entry(): void
    {
        $exception = new RuntimeException('Gateway timed out');

        $entry = $this->writer()->write(log_container(WidgetLog::class), new Scope, 'exception', 'Charge failed', [], $exception);

        $this->assertSame(RuntimeException::class, $entry->exception_class);
        $this->assertSame('Gateway timed out', $entry->exception_message);
        $this->assertSame(__FILE__, $entry->exception_file);
    }

    public function test_nothing_is_stored_for_a_level_the_container_does_not_accept(): void
    {
        $entry = $this->writer()->write(log_container(ProblemsLog::class), new Scope, 'debug', 'noise');

        $this->assertFalse($entry->exists);
        $this->assertDatabaseCount('log_entries', 0);
    }

    public function test_nothing_is_stored_when_a_before_hook_vetoes(): void
    {
        $entry = $this->writer()->write(log_container(HookedLog::class), new Scope, 'debug', 'dropped');

        $this->assertFalse($entry->exists);
        $this->assertDatabaseCount('log_entries', 0);
    }

    private function writer(): EntryWriter
    {
        return app(EntryWriter::class);
    }
}
