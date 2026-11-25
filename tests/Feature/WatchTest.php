<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Event;
use Keaton\LogContainers\Events\LogWatchTriggered;
use Keaton\LogContainers\Tests\Fixtures\ThrowingReporter;
use Keaton\LogContainers\Tests\Fixtures\Watches\BrokenWatch;
use Keaton\LogContainers\Tests\Fixtures\Watches\ThrowingMessageWatch;
use Keaton\LogContainers\Tests\Fixtures\Watches\WidgetErrorWatch;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;

class WatchTest extends TestCase
{
    /**
     * @var array<int, LogWatchTriggered>
     */
    private array $fired = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fired = [];
        Event::listen(LogWatchTriggered::class, function (LogWatchTriggered $event): void {
            $this->fired[] = $event;
        });
    }

    public function test_a_matching_entry_fires_the_watch_and_is_recorded(): void
    {
        config()->set('log-containers.watches', [
            WidgetErrorWatch::class => ['containers' => ['widgets']],
        ]);

        $log = log_container(WidgetLog::class);
        $log->info('fine');
        $entry = $log->error('broken');

        $this->assertCount(1, $this->fired);
        $this->assertSame('error: broken', $this->fired[0]->message);
        $this->assertSame([WidgetErrorWatch::class], $entry->fresh()->triggered_watches);
    }

    public function test_a_watch_only_sees_its_own_containers(): void
    {
        config()->set('log-containers.watches', [
            WidgetErrorWatch::class => ['containers' => ['something-else']],
        ]);

        log_container(WidgetLog::class)->error('broken');

        $this->assertCount(0, $this->fired);
    }

    public function test_a_wildcard_watch_sees_every_container(): void
    {
        config()->set('log-containers.watches', [
            WidgetErrorWatch::class => ['containers' => ['*']],
        ]);

        log_container(WidgetLog::class)->error('broken');

        $this->assertCount(1, $this->fired);
    }

    public function test_a_disabled_watch_never_fires(): void
    {
        config()->set('log-containers.watches', [
            WidgetErrorWatch::class => ['containers' => ['*'], 'enabled' => false],
        ]);

        log_container(WidgetLog::class)->error('broken');

        $this->assertCount(0, $this->fired);
    }

    public function test_a_throwing_watch_does_not_break_the_write(): void
    {
        config()->set('log-containers.watches', [
            BrokenWatch::class => ['containers' => ['*']],
        ]);

        $entry = log_container(WidgetLog::class)->error('still stored');

        $this->assertTrue($entry->exists);
        $this->assertDatabaseCount('log_entries', 1);
        $this->assertCount(0, $this->fired);
    }

    public function test_a_watch_that_throws_building_its_message_does_not_break_the_write(): void
    {
        config()->set('log-containers.watches', [
            ThrowingMessageWatch::class => ['containers' => ['*']],
        ]);

        $entry = log_container(WidgetLog::class)->error('still stored');

        $this->assertTrue($entry->exists);
        $this->assertDatabaseCount('log_entries', 1);
        $this->assertNull($entry->fresh()->triggered_watches);
    }

    public function test_a_reporter_that_itself_throws_does_not_break_the_write(): void
    {
        // Bind an exception handler whose report() throws. A broken watch hands
        // its failure to report(); if report() itself can throw unguarded, that
        // throw would escape the log write. Binding this proves it cannot.
        $this->app->instance(ExceptionHandler::class, new ThrowingReporter);

        config()->set('log-containers.watches', [
            BrokenWatch::class => ['containers' => ['*']],
        ]);

        $entry = log_container(WidgetLog::class)->error('still stored');

        $this->assertTrue($entry->exists);
        $this->assertDatabaseCount('log_entries', 1);
        $this->assertNull($entry->fresh()->triggered_watches);
        $this->assertCount(0, $this->fired);
    }
}
