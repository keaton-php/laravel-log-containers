<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Tests\Fixtures\CappedLog;
use Keaton\LogContainers\Tests\Fixtures\Widget;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;

class TrimTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Make the probabilistic trim certain, so the test is deterministic.
        config()->set('log-containers.trim.odds', 100);
    }

    public function test_the_oldest_rows_are_trimmed_down_to_the_cap(): void
    {
        $log = log_container(CappedLog::class);

        foreach (range(1, 10) as $n) {
            $log->info("m{$n}");
        }

        $this->assertSame(3, LogEntry::forContainer('capped')->count());
        $this->assertSame('m8', LogEntry::forContainer('capped')->orderBy('id')->value('message'));
    }

    public function test_trimming_removes_the_tags_of_trimmed_entries(): void
    {
        $widget = Widget::create(['name' => 'gizmo']);
        $log = log_container(CappedLog::class)->tagWith($widget);

        foreach (range(1, 10) as $n) {
            $log->info("m{$n}");
        }

        $this->assertDatabaseCount('log_entries', 3);
        $this->assertDatabaseCount('log_tags', 3);
    }

    public function test_a_container_with_no_cap_is_never_trimmed(): void
    {
        $log = log_container(WidgetLog::class);

        foreach (range(1, 10) as $n) {
            $log->info("m{$n}");
        }

        $this->assertDatabaseCount('log_entries', 10);
    }

    public function test_a_config_cap_overrides_the_container(): void
    {
        config()->set('log-containers.containers', [
            WidgetLog::class => ['max_entries' => 2],
        ]);

        $log = log_container(WidgetLog::class);

        foreach (range(1, 6) as $n) {
            $log->info("m{$n}");
        }

        $this->assertSame(2, LogEntry::forContainer('widgets')->count());
    }
}
