<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Writing;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Keaton\LogContainers\LogContainer;

/**
 * The settings a container runs with: what config/log-containers.php says for
 * its class, falling back to what the class declares.
 *
 * @internal
 */
final class ContainerSettings
{
    public function __construct(
        private Repository $config,
    ) {}

    public function enabled(LogContainer $container): bool
    {
        if (! (bool) $this->config->get('log-containers.enabled', true)) {
            return false;
        }

        return $this->config->get('log-containers.containers.'.$container::class.'.enabled', true) !== false;
    }

    public function allows(LogContainer $container, string $level): bool
    {
        $allowed = $this->levels($container);

        return $allowed === null || in_array($level, $allowed, true);
    }

    public function maxEntries(LogContainer $container): ?int
    {
        $override = $this->config->get('log-containers.containers.'.$container::class.'.max_entries');

        if (is_int($override)) {
            return $override;
        }

        return $container->maxEntries();
    }

    /**
     * The levels the container accepts, or null for all of them.
     *
     * @return array<int, string>|null
     */
    private function levels(LogContainer $container): ?array
    {
        $override = $this->config->get('log-containers.containers.'.$container::class);

        if (! is_array($override)) {
            return $container->allowedLevels();
        }

        $hasLevels = array_key_exists('levels', $override);
        $hasLevel = array_key_exists('level', $override);

        // Both options are resolved even though [levels] wins, so a malformed
        // [level] is never ignored just because [levels] is also set.
        $levels = $hasLevels ? $this->levelsOption($container::class, $override['levels']) : null;
        $preset = $hasLevel ? $this->levelOption($container::class, $override['level']) : null;

        if ($hasLevels) {
            return $levels;
        }

        if ($hasLevel) {
            return $preset;
        }

        return $container->allowedLevels();
    }

    /**
     * The explicit [levels] list of a container.
     *
     * @return array<int, string>
     */
    private function levelsOption(string $class, mixed $levels): array
    {
        if (! is_array($levels)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid [levels] configured for container [%s]: expected an array of level names, got %s.',
                $class,
                get_debug_type($levels),
            ));
        }

        return $this->validate($levels, sprintf('log-containers.containers.%s.levels', $class));
    }

    /**
     * The [level] preset of a container, as a list of levels.
     *
     * @return array<int, string>
     */
    private function levelOption(string $class, mixed $level): array
    {
        if (! is_string($level)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid [level] configured for container [%s]: expected a level preset name, got %s.',
                $class,
                get_debug_type($level),
            ));
        }

        $preset = $this->config->get('log-containers.level_presets.'.$level);

        if (! is_array($preset)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown level preset [%s] configured for container [%s]. Define it under log-containers.level_presets.',
                $level,
                $class,
            ));
        }

        return $this->validate($preset, sprintf('log-containers.level_presets.%s', $level));
    }

    /**
     * An unknown level in a configured list would drop every entry at that
     * level without a word, so it throws instead.
     *
     * @param  array<array-key, mixed>  $levels
     * @return array<int, string>
     */
    private function validate(array $levels, string $source): array
    {
        $validated = [];

        foreach ($levels as $level) {
            if (! is_string($level) || ! in_array($level, LogContainer::LEVELS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid log level [%s] configured at [%s]. Allowed levels are: %s.',
                    is_string($level) ? $level : get_debug_type($level),
                    $source,
                    implode(', ', LogContainer::LEVELS),
                ));
            }

            $validated[] = $level;
        }

        return $validated;
    }
}
