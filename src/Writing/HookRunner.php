<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Writing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;
use LogicException;
use ReflectionMethod;

/**
 * Calls the hooks a container defines: beforeLog() and before{Level}() ahead
 * of the write, onLog() and on{Level}() after it. Hooks are called through the
 * service container, so they can type-hint a service.
 *
 * @internal
 */
final class HookRunner
{
    public function __construct(
        private Container $app,
    ) {}

    /**
     * Run the before hooks. A hook may return changed attributes, or false to
     * drop the entry.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|false
     */
    public function before(LogContainer $container, string $level, array $attributes): array|false
    {
        // The after hooks are checked here as well, so a hook that can't be
        // called stops the write before the entry is stored.
        $this->refuseHiddenHooks($container, $level);

        foreach ($this->hooks($container, 'before', $level) as $method) {
            /** @var array<string, mixed>|false $result */
            $result = $this->app->call($container->{$method}(...), ['attributes' => $attributes]);

            if ($result === false) {
                return false;
            }

            if (is_array($result)) {
                $attributes = $result;
            }
        }

        return $attributes;
    }

    public function after(LogContainer $container, string $level, LogEntry $entry): void
    {
        // The entry is passed by name and by type, so the hook's parameter can
        // have any name. Without the type key the service container would
        // build an empty LogEntry for a parameter that isn't called $entry.
        foreach ($this->hooks($container, 'on', $level) as $method) {
            $this->app->call($container->{$method}(...), ['entry' => $entry, LogEntry::class => $entry]);
        }
    }

    /**
     * The hook methods the container defines for this level, general one first.
     *
     * @return array<int, string>
     */
    private function hooks(LogContainer $container, string $prefix, string $level): array
    {
        return array_values(array_filter(
            [$prefix.'Log', $prefix.Str::studly($level)],
            static fn (string $method): bool => method_exists($container, $method),
        ));
    }

    /**
     * method_exists() also finds protected and private methods, which the
     * service container can't call. Skipping one would silently lose a veto,
     * so it throws.
     */
    private function refuseHiddenHooks(LogContainer $container, string $level): void
    {
        foreach ([...$this->hooks($container, 'before', $level), ...$this->hooks($container, 'on', $level)] as $method) {
            if (! (new ReflectionMethod($container, $method))->isPublic()) {
                throw new LogicException(sprintf(
                    'Hook [%s::%s] must be public. Hooks are called through the service container.',
                    $container::class,
                    $method,
                ));
            }
        }
    }
}
