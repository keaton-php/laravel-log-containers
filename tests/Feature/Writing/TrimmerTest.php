<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature\Writing;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Tests\Fixtures\CappedLog;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use Keaton\LogContainers\Writing\Trimmer;

class TrimmerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No trimming while the rows are written; each test trims by hand.
        config()->set('log-containers.containers', [CappedLog::class => ['max_entries' => 0]]);
    }

    public function test_trim_deletes_the_oldest_rows_and_their_tags(): void
    {
        $log = log_container(WidgetLog::class)->tagWith('batch');

        foreach (range(1, 5) as $n) {
            $log->info("m{$n}");
        }

        app(Trimmer::class)->trim('widgets', 2);

        $this->assertSame(['m4', 'm5'], LogEntry::forContainer('widgets')->orderBy('id')->pluck('message')->all());
        $this->assertDatabaseCount('log_tags', 2);
    }

    public function test_trim_leaves_other_containers_alone(): void
    {
        log_container(WidgetLog::class)->info('widget');
        log_container(CappedLog::class)->info('one');
        log_container(CappedLog::class)->info('two');

        app(Trimmer::class)->trim('capped', 1);

        $this->assertSame(1, LogEntry::forContainer('widgets')->count());
        $this->assertSame(['two'], LogEntry::forContainer('capped')->pluck('message')->all());
    }

    public function test_a_container_without_a_cap_is_never_trimmed(): void
    {
        config()->set('log-containers.trim.odds', 100);

        $log = log_container(WidgetLog::class);

        foreach (range(1, 4) as $n) {
            $log->info("m{$n}");
        }

        app(Trimmer::class)->maybeTrim($log);

        $this->assertSame(4, LogEntry::forContainer('widgets')->count());
    }

    public function test_at_full_odds_a_capped_container_is_trimmed_to_its_cap(): void
    {
        $log = log_container(CappedLog::class);

        foreach (range(1, 6) as $n) {
            $log->info("m{$n}");
        }

        config()->set('log-containers.trim.odds', 100);
        config()->set('log-containers.containers', []);

        app(Trimmer::class)->maybeTrim($log);

        $this->assertSame(3, LogEntry::forContainer('capped')->count());
    }
}
