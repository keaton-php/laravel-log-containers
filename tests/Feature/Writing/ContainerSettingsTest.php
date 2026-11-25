<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature\Writing;

use InvalidArgumentException;
use Keaton\LogContainers\Tests\Fixtures\CappedLog;
use Keaton\LogContainers\Tests\Fixtures\ProblemsLog;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use Keaton\LogContainers\Writing\ContainerSettings;

class ContainerSettingsTest extends TestCase
{
    public function test_a_container_is_on_by_default(): void
    {
        $this->assertTrue($this->settings()->enabled(log_container(WidgetLog::class)));
    }

    public function test_the_package_switch_turns_every_container_off(): void
    {
        config()->set('log-containers.enabled', false);

        $this->assertFalse($this->settings()->enabled(log_container(WidgetLog::class)));
    }

    public function test_one_container_can_be_turned_off(): void
    {
        config()->set('log-containers.containers', [WidgetLog::class => ['enabled' => false]]);

        $this->assertFalse($this->settings()->enabled(log_container(WidgetLog::class)));
        $this->assertTrue($this->settings()->enabled(log_container(ProblemsLog::class)));
    }

    public function test_every_level_is_allowed_until_the_container_narrows_the_list(): void
    {
        $this->assertTrue($this->settings()->allows(log_container(WidgetLog::class), 'debug'));

        $this->assertTrue($this->settings()->allows(log_container(ProblemsLog::class), 'error'));
        $this->assertFalse($this->settings()->allows(log_container(ProblemsLog::class), 'debug'));
    }

    public function test_a_configured_levels_list_wins_over_the_class(): void
    {
        config()->set('log-containers.containers', [ProblemsLog::class => ['levels' => ['debug']]]);

        $this->assertTrue($this->settings()->allows(log_container(ProblemsLog::class), 'debug'));
        $this->assertFalse($this->settings()->allows(log_container(ProblemsLog::class), 'error'));
    }

    public function test_a_configured_preset_wins_over_the_class(): void
    {
        config()->set('log-containers.level_presets.quiet', ['emergency']);
        config()->set('log-containers.containers', [ProblemsLog::class => ['level' => 'quiet']]);

        $this->assertTrue($this->settings()->allows(log_container(ProblemsLog::class), 'emergency'));
        $this->assertFalse($this->settings()->allows(log_container(ProblemsLog::class), 'error'));
    }

    public function test_the_levels_list_wins_over_the_preset(): void
    {
        config()->set('log-containers.containers', [WidgetLog::class => ['levels' => ['info'], 'level' => 'problems']]);

        $this->assertTrue($this->settings()->allows(log_container(WidgetLog::class), 'info'));
        $this->assertFalse($this->settings()->allows(log_container(WidgetLog::class), 'error'));
    }

    public function test_a_bad_preset_throws_even_when_a_levels_list_is_set(): void
    {
        config()->set('log-containers.containers', [WidgetLog::class => ['levels' => ['info'], 'level' => 'no-such-preset']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown level preset [no-such-preset]');

        $this->settings()->allows(log_container(WidgetLog::class), 'info');
    }

    public function test_an_unknown_level_in_config_throws(): void
    {
        config()->set('log-containers.containers', [WidgetLog::class => ['levels' => ['info', 'loud']]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level [loud]');

        $this->settings()->allows(log_container(WidgetLog::class), 'info');
    }

    public function test_the_row_cap_comes_from_config_first_then_the_class(): void
    {
        $this->assertNull($this->settings()->maxEntries(log_container(WidgetLog::class)));
        $this->assertSame(3, $this->settings()->maxEntries(log_container(CappedLog::class)));

        config()->set('log-containers.containers', [CappedLog::class => ['max_entries' => 50]]);

        $this->assertSame(50, $this->settings()->maxEntries(log_container(CappedLog::class)));
    }

    private function settings(): ContainerSettings
    {
        return app(ContainerSettings::class);
    }
}
