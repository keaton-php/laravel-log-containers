<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogTag;
use Keaton\LogContainers\Tests\Fixtures\PlainWidget;
use Keaton\LogContainers\Tests\Fixtures\Widget;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;

class TagTest extends TestCase
{
    public function test_tagging_a_model_links_entries_and_is_queryable(): void
    {
        $widget = Widget::create(['name' => 'gizmo']);

        $log = log_container(WidgetLog::class)->tagWith($widget);
        $log->info('first');
        $log->info('second');

        $this->assertSame(2, LogEntry::taggedWith($widget)->count());

        $tag = LogTag::query()->firstOrFail();
        $this->assertSame($widget->getMorphClass(), $tag->taggable_type);
        $this->assertSame('1', $tag->taggable_id);
        $this->assertSame('Widget: gizmo', $tag->label);
    }

    public function test_a_string_tag_is_label_only(): void
    {
        log_container(WidgetLog::class)->tagWith('batch-42')->info('tagged');

        $tag = LogTag::query()->firstOrFail();
        $this->assertNull($tag->taggable_type);
        $this->assertSame('batch-42', $tag->label);
        $this->assertFalse($tag->isModelTag());
    }

    public function test_the_same_tag_is_not_added_twice(): void
    {
        $widget = Widget::create(['name' => 'gizmo']);

        log_container(WidgetLog::class)->tagWith($widget)->tagWith($widget)->info('once');

        $this->assertDatabaseCount('log_tags', 1);
    }

    public function test_clear_tags_stops_the_tagging(): void
    {
        $widget = Widget::create(['name' => 'gizmo']);

        $log = log_container(WidgetLog::class)->tagWith($widget);
        $log->info('tagged');
        $log->clearTags()->info('bare');

        $this->assertSame(1, LogEntry::query()->where('message', 'tagged')->firstOrFail()->tags()->count());
        $this->assertSame(0, LogEntry::query()->where('message', 'bare')->firstOrFail()->tags()->count());
    }

    public function test_a_model_without_a_label_contract_falls_back_to_class_and_id(): void
    {
        $widget = PlainWidget::create(['name' => 'plain']);

        log_container(WidgetLog::class)->tagWith($widget)->info('tagged');

        $this->assertSame('PlainWidget #1', LogTag::query()->firstOrFail()->label);
    }
}
