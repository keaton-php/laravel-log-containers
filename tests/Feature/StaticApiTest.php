<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use BadMethodCallException;
use InvalidArgumentException;
use Keaton\LogContainers\Facades\LogContainers;
use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogSession;
use Keaton\LogContainers\Tests\Fixtures\CustomLog;
use Keaton\LogContainers\Tests\Fixtures\SecretLog;
use Keaton\LogContainers\Tests\Fixtures\Widget;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use RuntimeException;

/**
 * The static facade-style API: OrderLog::info(...) reads like Log::info(...)
 * and routes through the same manager, container and write path as the
 * instance/helper form.
 */
class StaticApiTest extends TestCase
{
    public function test_a_static_level_call_writes_the_same_row_as_the_instance_call(): void
    {
        WidgetLog::info('Order placed', ['order_id' => 42]);
        log_container(WidgetLog::class)->info('Order placed', ['order_id' => 42]);

        $rows = LogEntry::forContainer('widgets')->orderBy('id')->get();

        $this->assertCount(2, $rows);
        [$static, $instance] = [$rows[0], $rows[1]];

        $this->assertSame($instance->level, $static->level);
        $this->assertSame($instance->message, $static->message);
        $this->assertSame($instance->container_key, $static->container_key);
        $this->assertSame($instance->context, $static->context);
        $this->assertSame('info', $static->level);
        $this->assertSame(['source' => 'widget-log', 'order_id' => 42], $static->context);
    }

    public function test_a_static_level_call_returns_the_stored_entry(): void
    {
        $entry = WidgetLog::info('Order placed');

        $this->assertInstanceOf(LogEntry::class, $entry);
        $this->assertTrue($entry->exists);
        $this->assertSame('widgets', $entry->container_key);
        $this->assertSame('info', $entry->level);
    }

    public function test_every_level_is_callable_statically(): void
    {
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'success'] as $level) {
            WidgetLog::{$level}('a message');
        }

        $this->assertSame(9, LogEntry::forContainer('widgets')->count());
        $this->assertSame(1, LogEntry::forContainer('widgets')->where('level', 'emergency')->count());
    }

    public function test_the_static_exception_level_records_the_throwable(): void
    {
        $entry = WidgetLog::exception('Charge failed', new RuntimeException('gateway timed out'));

        $this->assertSame('exception', $entry->level);
        $this->assertSame(RuntimeException::class, $entry->exception_class);
        $this->assertSame('gateway timed out', $entry->exception_message);
    }

    public function test_static_doing_opens_a_session_and_scopes_its_entries(): void
    {
        WidgetLog::doing('fulfilling', function (LogContainer $log): void {
            $log->info('reserved stock');
            $log->success('fulfilled');
        });

        $session = LogSession::query()->where('container_key', 'widgets')->firstOrFail();
        $this->assertSame(LogSession::STATUS_COMPLETED, $session->status);
        $this->assertSame(2, $session->entry_count);

        $entry = LogEntry::forContainer('widgets')->where('message', 'reserved stock')->firstOrFail();
        $this->assertSame('fulfilling', $entry->doing);
        $this->assertSame($session->id, $entry->session_id);
    }

    public function test_static_during_stamps_a_scope_label_without_a_session(): void
    {
        WidgetLog::during('nightly', function (LogContainer $log): void {
            $log->info('tallying');
        });

        $this->assertDatabaseCount('log_sessions', 0);
        $this->assertSame('nightly', LogEntry::forContainer('widgets')->firstOrFail()->during);
    }

    public function test_static_context_chains_into_a_scope(): void
    {
        WidgetLog::context(['ip' => '203.0.113.9'])
            ->during('checkout', function (LogContainer $log): void {
                $log->warning('Validation failed', ['field' => 'total']);
            });

        $entry = LogEntry::forContainer('widgets')->firstOrFail();
        $this->assertSame('checkout', $entry->during);
        $this->assertSame('203.0.113.9', $entry->context['ip']);
        $this->assertSame('total', $entry->context['field']);
    }

    public function test_static_tag_with_links_entries_to_a_record(): void
    {
        $widget = Widget::create(['name' => 'sticky']);

        WidgetLog::tagWith($widget)->info('Touched the widget');

        $this->assertSame(1, LogEntry::taggedWith($widget)->count());
    }

    public function test_a_static_call_inside_a_block_joins_the_block(): void
    {
        $widget = Widget::create(['name' => 'sticky']);

        WidgetLog::context(['ip' => '203.0.113.9'])->tagWith($widget)->during('nightly', function (LogContainer $log): void {
            $log->doing('fulfilling', function (): void {
                WidgetLog::info('from deeper code');
                log_container(WidgetLog::class)->info('from the helper');
                LogContainers::container('widgets')->info('from the facade');
            });
        });

        $session = LogSession::query()->where('container_key', 'widgets')->firstOrFail();
        $this->assertSame(3, $session->entry_count);

        foreach (LogEntry::forContainer('widgets')->get() as $entry) {
            $this->assertSame('fulfilling', $entry->doing, $entry->message);
            $this->assertSame('nightly', $entry->during, $entry->message);
            $this->assertSame($session->id, $entry->session_id, $entry->message);
            $this->assertSame('203.0.113.9', $entry->context['ip'], $entry->message);
        }

        $this->assertSame(3, LogEntry::taggedWith($widget)->count());
    }

    public function test_a_static_call_inside_a_during_block_joins_it(): void
    {
        WidgetLog::during('nightly', function (): void {
            WidgetLog::info('from deeper code');
        });

        $this->assertSame('nightly', LogEntry::forContainer('widgets')->firstOrFail()->during);
    }

    public function test_a_static_block_inside_a_block_is_a_step_of_it(): void
    {
        WidgetLog::doing('fulfilling', function (): void {
            WidgetLog::doing('reserving stock', function (): void {
                WidgetLog::info('reserved');
            });
            WidgetLog::info('back in the outer block');
        });

        $step = LogSession::query()->where('name', 'reserving stock')->firstOrFail();
        $this->assertSame('fulfilling', $step->parent?->name);
        $this->assertSame($step->id, LogEntry::query()->where('message', 'reserved')->firstOrFail()->session_id);
        $this->assertSame('fulfilling', LogEntry::query()->where('message', 'back in the outer block')->firstOrFail()->doing);
    }

    public function test_what_a_joining_call_sets_does_not_change_the_block(): void
    {
        $widget = Widget::create(['name' => 'sticky']);

        WidgetLog::doing('fulfilling', function (LogContainer $log) use ($widget): void {
            WidgetLog::tagWith($widget)->context(['deeper' => true])->info('tagged by deeper code');
            $log->info('from the block');
            WidgetLog::info('a second deeper call');
        });

        $this->assertSame(['tagged by deeper code'], LogEntry::taggedWith($widget)->pluck('message')->all());
        $this->assertArrayNotHasKey('deeper', LogEntry::query()->where('message', 'from the block')->firstOrFail()->context ?? []);
        $this->assertArrayNotHasKey('deeper', LogEntry::query()->where('message', 'a second deeper call')->firstOrFail()->context ?? []);
    }

    public function test_a_block_of_another_container_is_not_joined(): void
    {
        WidgetLog::doing('fulfilling', function (): void {
            SecretLog::info('another container');
        });

        $entry = LogEntry::query()->where('message', 'another container')->firstOrFail();
        $this->assertNull($entry->doing);
        $this->assertNull($entry->session_id);
    }

    public function test_a_container_with_a_constructor_of_its_own_joins_too(): void
    {
        log_container(CustomLog::class)->doing('auditing', function (): void {
            log_container(CustomLog::class)->audit('from deeper code');
        });

        $entry = LogEntry::forContainer('custom')->where('level', 'notice')->firstOrFail();
        $this->assertSame('auditing', $entry->doing);
        $this->assertSame('FROM DEEPER CODE', $entry->message);
        $this->assertNotNull($entry->session_id);
    }

    public function test_a_container_resolved_before_the_block_keeps_its_own_scope(): void
    {
        $held = log_container(WidgetLog::class);

        WidgetLog::doing('fulfilling', function () use ($held): void {
            $held->info('held from before');
        });

        $this->assertNull(LogEntry::query()->where('message', 'held from before')->firstOrFail()->doing);
    }

    public function test_a_static_call_after_the_block_starts_clean_again(): void
    {
        WidgetLog::doing('fulfilling', fn () => null);
        WidgetLog::info('after the block');

        try {
            WidgetLog::during('nightly', function (): void {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        WidgetLog::info('after the failed block');

        foreach (['after the block', 'after the failed block'] as $message) {
            $entry = LogEntry::query()->where('message', $message)->firstOrFail();
            $this->assertNull($entry->doing, $message);
            $this->assertNull($entry->during, $message);
            $this->assertNull($entry->session_id, $message);
        }
    }

    public function test_the_fake_catches_entries_written_statically(): void
    {
        LogContainers::fake();

        WidgetLog::info('one');
        WidgetLog::error('two');

        LogContainers::assertLogged(WidgetLog::class);
        LogContainers::assertLogged(WidgetLog::class, 'error');
        LogContainers::assertNotLogged(WidgetLog::class, 'critical');
        LogContainers::assertCount(WidgetLog::class, 2);
        LogContainers::assertCount(WidgetLog::class, 1, 'error');
    }

    public function test_the_instance_and_helper_forms_still_work(): void
    {
        log_container(WidgetLog::class)->info('via helper');
        LogContainers::container(WidgetLog::class)->error('via facade container');

        $this->assertSame(2, LogEntry::forContainer('widgets')->count());
        $this->assertSame(1, LogEntry::forContainer('widgets')->where('level', 'error')->count());
    }

    public function test_an_unknown_static_method_is_refused(): void
    {
        $this->expectException(BadMethodCallException::class);

        WidgetLog::frobnicate('nope'); // @phpstan-ignore staticMethod.notFound
    }

    public function test_an_unknown_instance_method_is_refused(): void
    {
        $this->expectException(BadMethodCallException::class);

        log_container(WidgetLog::class)->frobnicate('nope'); // @phpstan-ignore method.notFound
    }

    public function test_a_non_string_message_is_refused_rather_than_stored(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WidgetLog::info(42); // @phpstan-ignore argument.type
    }

    public function test_a_non_array_context_is_refused_rather_than_stored(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WidgetLog::info('a message', 'not-an-array'); // @phpstan-ignore argument.type
    }

    public function test_a_static_call_to_the_protected_writer_is_refused(): void
    {
        // write() is protected: the magic path must never expose it, so an
        // undocumented name is refused before the container is even resolved.
        $this->expectException(BadMethodCallException::class);

        WidgetLog::write('info', 'should never reach the protected writer'); // @phpstan-ignore staticMethod.notFound
    }

    public function test_an_instance_call_to_the_protected_writer_is_refused(): void
    {
        $this->expectException(BadMethodCallException::class);

        log_container(WidgetLog::class)->write('info', 'should never reach the protected writer'); // @phpstan-ignore method.notFound
    }

    public function test_a_static_level_call_accepts_named_arguments(): void
    {
        $entry = WidgetLog::info(message: 'Named statically', context: ['order_id' => 7]);

        $this->assertInstanceOf(LogEntry::class, $entry);
        $this->assertSame('info', $entry->level);
        $this->assertSame('Named statically', $entry->message);
        $this->assertSame(7, $entry->context['order_id']);
    }

    public function test_an_instance_level_call_accepts_message_and_context_named(): void
    {
        $entry = log_container(WidgetLog::class)->info(message: 'Named on the handle', context: ['order_id' => 11]);

        $this->assertInstanceOf(LogEntry::class, $entry);
        $this->assertSame('info', $entry->level);
        $this->assertSame('Named on the handle', $entry->message);
        $this->assertSame(11, $entry->context['order_id']);
    }

    public function test_a_named_scope_argument_is_honored(): void
    {
        WidgetLog::during(label: 'nightly', callback: function (LogContainer $log): void {
            $log->info('tallying');
        });

        $this->assertSame('nightly', LogEntry::forContainer('widgets')->firstOrFail()->during);
    }

    public function test_an_unexpected_argument_to_a_level_is_refused_on_the_instance_route(): void
    {
        // A key that is neither the message nor the context must throw rather
        // than be silently dropped. A dynamic method name keeps the bad call
        // out of PHPStan's resolved @method signature.
        $this->expectException(InvalidArgumentException::class);

        $log = log_container(WidgetLog::class);
        $method = 'info';
        $log->{$method}(color: 'not a real parameter');
    }

    public function test_an_unexpected_argument_to_a_level_is_refused_on_the_static_route(): void
    {
        // The static route normalizes arguments through the same path, so an
        // unexpected named argument must be refused there too.
        $this->expectException(InvalidArgumentException::class);

        $method = 'info';
        WidgetLog::{$method}(color: 'not a real parameter');
    }

    public function test_a_message_supplied_positionally_and_by_name_is_refused_on_the_instance_route(): void
    {
        // info('first', message: 'second') is ambiguous: refuse it rather than
        // silently keep one value and drop the other.
        $this->expectException(InvalidArgumentException::class);

        $log = log_container(WidgetLog::class);
        $method = 'info';
        $log->{$method}('first', message: 'second');
    }

    public function test_a_message_supplied_positionally_and_by_name_is_refused_on_the_static_route(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $method = 'info';
        WidgetLog::{$method}('first', message: 'second');
    }

    public function test_a_context_supplied_positionally_and_by_name_is_refused_on_the_instance_route(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $log = log_container(WidgetLog::class);
        $method = 'info';
        $log->{$method}('a message', ['positional' => true], context: ['named' => true]);
    }

    public function test_a_context_supplied_positionally_and_by_name_is_refused_on_the_static_route(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $method = 'info';
        WidgetLog::{$method}('a message', ['positional' => true], context: ['named' => true]);
    }

    public function test_no_row_is_written_when_a_duplicate_binding_is_refused(): void
    {
        // The guard must fire before anything is persisted, so an ambiguous
        // call leaves no partial row behind.
        $method = 'info';

        try {
            WidgetLog::{$method}('first', message: 'second');
            $this->fail('A duplicate positional/named binding should have thrown.');
        } catch (InvalidArgumentException $e) {
            // expected
        }

        $this->assertSame(0, LogEntry::forContainer('widgets')->count());
    }
}
