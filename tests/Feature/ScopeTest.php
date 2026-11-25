<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogSession;
use Keaton\LogContainers\Tests\Fixtures\FailingExceptionLog;
use Keaton\LogContainers\Tests\Fixtures\SecretLog;
use Keaton\LogContainers\Tests\Fixtures\Widget;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use RuntimeException;

class ScopeTest extends TestCase
{
    public function test_doing_opens_a_completed_timed_session_and_stamps_entries(): void
    {
        log_container(WidgetLog::class)->doing('building', function (LogContainer $log): void {
            $log->info('step one');
            $log->error('step two failed');
        });

        $session = LogSession::query()->firstOrFail();
        $this->assertSame('building', $session->name);
        $this->assertSame(LogSession::STATUS_COMPLETED, $session->status);
        $this->assertSame(2, $session->entry_count);
        $this->assertSame(1, $session->error_count);
        $this->assertNotNull($session->duration_ms);

        $entry = LogEntry::query()->where('message', 'step one')->firstOrFail();
        $this->assertSame('building', $entry->doing);
        $this->assertSame($session->id, $entry->session_id);
    }

    public function test_doing_returns_the_callbacks_value(): void
    {
        $result = log_container(WidgetLog::class)->doing('compute', fn () => 42);

        $this->assertSame(42, $result);
    }

    public function test_nested_doing_blocks_get_their_own_sessions(): void
    {
        log_container(WidgetLog::class)->doing('outer', function (LogContainer $log): void {
            $log->info('in outer');
            $log->doing('inner', function (LogContainer $log): void {
                $log->info('in inner');
            });
        });

        $this->assertDatabaseCount('log_sessions', 2);

        $inner = LogEntry::query()->where('message', 'in inner')->firstOrFail();
        $this->assertSame('inner', $inner->doing);
        $this->assertSame('inner', LogSession::query()->whereKey($inner->session_id)->value('name'));
    }

    public function test_a_nested_doing_block_is_a_child_of_the_block_around_it(): void
    {
        log_container(WidgetLog::class)->doing('outer', function (LogContainer $log): void {
            $log->doing('first step', fn () => null);
            $log->during('a label only', function (LogContainer $log): void {
                $log->doing('second step', function (LogContainer $log): void {
                    $log->doing('deepest', fn () => null);
                });
            });
        });
        log_container(WidgetLog::class)->doing('afterwards', fn () => null);

        $outer = LogSession::query()->where('name', 'outer')->firstOrFail();
        $second = LogSession::query()->where('name', 'second step')->firstOrFail();

        $this->assertNull($outer->parent_id);
        $this->assertNull($outer->parent);
        $this->assertSame(['first step', 'second step'], $outer->children()->orderBy('id')->pluck('name')->all());
        $this->assertSame(['deepest'], $second->children->pluck('name')->all());
        $this->assertSame('outer', $second->parent?->name);
        $this->assertNull(LogSession::query()->where('name', 'afterwards')->firstOrFail()->parent_id);
    }

    public function test_blocks_nest_to_any_depth(): void
    {
        $nest = function (LogContainer $log, int $level) use (&$nest): void {
            $log->doing("level {$level}", function (LogContainer $log) use (&$nest, $level): void {
                $log->info("in level {$level}");

                if ($level < 12) {
                    $nest($log, $level + 1);
                }
            });
        };

        $nest(log_container(WidgetLog::class), 1);

        $session = LogSession::query()->where('name', 'level 12')->firstOrFail();
        $chain = [];

        while ($session !== null) {
            $this->assertSame(1, $session->entry_count);
            $chain[] = $session->name;
            $session = $session->parent;
        }

        $this->assertSame(array_map(fn (int $level): string => "level {$level}", range(12, 1)), $chain);
        $this->assertSame(12, LogSession::query()->where('status', 'completed')->count());
    }

    public function test_a_block_of_another_container_is_a_child_too(): void
    {
        log_container(WidgetLog::class)->doing('outer', function (): void {
            SecretLog::doing('inner', fn () => null);
        });

        $inner = LogSession::query()->where('name', 'inner')->firstOrFail();

        $this->assertSame('outer', $inner->parent?->name);
        $this->assertNotSame($inner->container_key, $inner->parent?->container_key);
    }

    public function test_a_session_opened_by_hand_inside_a_block_is_a_child_of_it(): void
    {
        $byHand = log_container(WidgetLog::class)->doing('outer', fn (LogContainer $log): LogSession => $log->startSession('by hand'));

        $this->assertSame('outer', $byHand->parent?->name);
        $this->assertNull(log_container(WidgetLog::class)->startSession('on its own')->parent_id);
    }

    public function test_a_block_that_throws_is_not_the_parent_of_what_runs_next(): void
    {
        try {
            log_container(WidgetLog::class)->doing('fails', function (): void {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        log_container(WidgetLog::class)->doing('next', fn () => null);

        $this->assertNull(LogSession::query()->where('name', 'next')->firstOrFail()->parent_id);
    }

    public function test_a_throwing_doing_block_fails_the_session_logs_and_rethrows(): void
    {
        try {
            log_container(WidgetLog::class)->doing('risky', function (LogContainer $log): void {
                $log->info('before the throw');

                throw new RuntimeException('kaboom');
            });
            $this->fail('the exception should have been rethrown');
        } catch (RuntimeException $e) {
            $this->assertSame('kaboom', $e->getMessage());
        }

        $this->assertSame(LogSession::STATUS_FAILED, LogSession::query()->firstOrFail()->status);
        $this->assertSame(1, LogEntry::query()->where('level', 'exception')->count());
    }

    public function test_during_stamps_a_label_without_a_session(): void
    {
        log_container(WidgetLog::class)->during('nightly', function (LogContainer $log): void {
            $log->info('tallying');
        });

        $this->assertDatabaseCount('log_sessions', 0);
        $this->assertSame('nightly', LogEntry::query()->firstOrFail()->during);
    }

    public function test_a_scope_restores_the_tags_it_started_with(): void
    {
        $widget = Widget::create(['name' => 'sticky']);

        $log = log_container(WidgetLog::class)->tagWith($widget);

        $log->doing('scope', function (LogContainer $log): void {
            $log->tagWith(Widget::create(['name' => 'temporary']));
            $log->info('inside');
        });

        $log->info('after');

        $this->assertSame(2, LogEntry::query()->where('message', 'inside')->firstOrFail()->tags()->count());
        $this->assertSame(1, LogEntry::query()->where('message', 'after')->firstOrFail()->tags()->count());
    }

    public function test_a_disabled_container_runs_the_doing_callback_but_writes_no_session_or_entries(): void
    {
        config()->set('log-containers.containers', [WidgetLog::class => ['enabled' => false]]);

        $result = log_container(WidgetLog::class)->doing('import', function (LogContainer $log) {
            $log->info('inside');

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertDatabaseCount('log_sessions', 0);
        $this->assertDatabaseCount('log_entries', 0);
    }

    public function test_a_failure_while_logging_the_exception_does_not_replace_the_blocks_exception(): void
    {
        $caught = null;

        try {
            log_container(FailingExceptionLog::class)->doing('risky', function (): void {
                throw new RuntimeException('the real failure');
            });
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame('the real failure', $caught->getMessage());
    }

    public function test_a_failure_while_logging_the_exception_does_not_replace_a_during_blocks_exception(): void
    {
        $caught = null;

        try {
            log_container(FailingExceptionLog::class)->during('risky', function (): void {
                throw new RuntimeException('the real failure');
            });
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame('the real failure', $caught->getMessage());
    }
}
