<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use InvalidArgumentException;
use Keaton\LogContainers\ExecutionContext;
use Keaton\LogContainers\LogContainerManager;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Tests\Fixtures\ProblemsLog;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class LogContainerTest extends TestCase
{
    public function test_a_level_method_stores_one_queryable_row(): void
    {
        $entry = log_container(WidgetLog::class)->info('Widget made', ['id' => 7]);

        $this->assertTrue($entry->exists);
        $this->assertDatabaseCount('log_entries', 1);
        $this->assertSame('widgets', $entry->container_key);
        $this->assertSame('info', $entry->level);
        $this->assertSame('Widget made', $entry->message);
        $this->assertSame(app(ExecutionContext::class)->id(), $entry->execution_id);
        $this->assertNotNull($entry->environment);
    }

    public function test_context_merges_default_then_instance_then_call(): void
    {
        $entry = log_container(WidgetLog::class)
            ->context(['request' => 'r-1'])
            ->info('Made', ['id' => 7]);

        $this->assertSame(['source' => 'widget-log', 'request' => 'r-1', 'id' => 7], $entry->context);
    }

    public function test_type_is_stamped_on_the_entry(): void
    {
        $entry = log_container(WidgetLog::class)->type('batch')->warning('Heads up');

        $this->assertSame('batch', $entry->type);
    }

    public function test_all_levels_write(): void
    {
        $log = log_container(WidgetLog::class);

        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'success'] as $level) {
            $log->{$level}('a '.$level);
        }

        $this->assertDatabaseCount('log_entries', 9);
        $this->assertSame(1, LogEntry::query()->where('level', 'critical')->count());
    }

    public function test_a_level_the_container_does_not_allow_is_dropped(): void
    {
        $log = log_container(ProblemsLog::class);
        $kept = $log->error('real problem');
        $dropped = $log->info('just chatter');

        $this->assertTrue($kept->exists);
        $this->assertFalse($dropped->exists);
        $this->assertDatabaseCount('log_entries', 1);
    }

    public function test_config_levels_override_the_container(): void
    {
        config()->set('log-containers.containers', [
            WidgetLog::class => ['levels' => ['error']],
        ]);

        $log = log_container(WidgetLog::class);

        $this->assertFalse($log->info('chatter')->exists);
        $this->assertTrue($log->error('problem')->exists);
    }

    public function test_an_unknown_level_preset_throws_rather_than_silently_ignoring_it(): void
    {
        config()->set('log-containers.containers', [
            WidgetLog::class => ['level' => 'does-not-exist'],
        ]);

        $this->expectException(InvalidArgumentException::class);

        log_container(WidgetLog::class)->error('problem');
    }

    /**
     * Every malformed level configuration must fail loudly rather than silently
     * dropping logs or ignoring the bad option. The last two cases are the
     * simultaneous levels+level path: a valid [levels] must not let a broken
     * [level] slip through unchecked.
     *
     * @param  array<string, mixed>  $override
     * @param  array<string, mixed>  $presets
     */
    #[DataProvider('invalidLevelConfigs')]
    public function test_a_malformed_level_configuration_throws(array $override, array $presets): void
    {
        config()->set('log-containers.containers', [WidgetLog::class => $override]);

        if ($presets !== []) {
            config()->set('log-containers.level_presets', $presets);
        }

        $this->expectException(InvalidArgumentException::class);

        log_container(WidgetLog::class)->error('trigger validation');
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    public static function invalidLevelConfigs(): array
    {
        return [
            'non-array levels' => [['levels' => 'error'], []],
            'non-string level' => [['level' => 123], []],
            'unknown level member' => [['levels' => ['error', 'bogus']], []],
            'malformed preset member' => [['level' => 'custom'], ['custom' => ['error', 'nope']]],
            'valid levels but non-string level' => [['levels' => ['error'], 'level' => 123], []],
            'valid levels but unknown preset level' => [['levels' => ['error'], 'level' => 'does-not-exist'], []],
        ];
    }

    public function test_a_disabled_container_returns_an_unsaved_entry_and_stores_nothing(): void
    {
        config()->set('log-containers.containers', [WidgetLog::class => ['enabled' => false]]);

        $entry = log_container(WidgetLog::class)->error('nope');

        $this->assertFalse($entry->exists);
        $this->assertDatabaseCount('log_entries', 0);
    }

    public function test_the_master_switch_turns_every_container_off(): void
    {
        config()->set('log-containers.enabled', false);

        log_container(WidgetLog::class)->error('nope');

        $this->assertDatabaseCount('log_entries', 0);
    }

    public function test_exception_records_the_throwable_details(): void
    {
        $entry = log_container(WidgetLog::class)->exception('It blew up', new RuntimeException('boom'));

        $this->assertSame('exception', $entry->level);
        $this->assertSame(RuntimeException::class, $entry->exception_class);
        $this->assertSame('boom', $entry->exception_message);
        $this->assertNotNull($entry->exception_line);
    }

    public function test_a_container_always_resolves_by_class(): void
    {
        log_container(WidgetLog::class)->info('by class');

        $this->assertDatabaseCount('log_entries', 1);
    }

    public function test_a_key_resolves_when_the_container_is_registered_in_config(): void
    {
        config()->set('log-containers.containers', [WidgetLog::class => []]);

        // No prior resolution by class this request: the key is found through
        // the configured container list alone.
        app(LogContainerManager::class)->container('widgets')->info('by key');

        $this->assertDatabaseCount('log_entries', 1);
    }

    public function test_a_key_resolves_after_its_class_was_resolved_this_request(): void
    {
        // Resolving by class registers the key for the rest of the request,
        // so a later by-key lookup finds it even without a config entry.
        log_container(WidgetLog::class)->info('by class');
        app(LogContainerManager::class)->container('widgets')->info('by key');

        $this->assertDatabaseCount('log_entries', 2);
    }

    public function test_an_unknown_key_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LogContainerManager::class)->container('nope');
    }
}
