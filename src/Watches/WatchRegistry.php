<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Watches;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Keaton\LogContainers\Events\LogWatchTriggered;
use Keaton\LogContainers\Models\LogEntry;
use Throwable;

/**
 * Resolves the watches registered in config and checks a freshly persisted
 * entry against the ones that apply to its container.
 */
class WatchRegistry
{
    /**
     * Resolved watch instances keyed by class, built once per registry.
     *
     * @var array<class-string<LogWatch>, LogWatch>
     */
    private array $instances = [];

    public function __construct(
        private Container $app,
        private Dispatcher $events,
        private Repository $config,
    ) {}

    /**
     * Check an entry against every enabled watch for its container. Each match
     * is recorded on the entry and dispatched as a LogWatchTriggered event.
     */
    public function inspect(LogEntry $entry): void
    {
        $triggered = [];

        foreach ($this->watchesForContainer($entry->container_key) as $class => $watch) {
            if ($this->evaluate($watch, $entry)) {
                $triggered[] = $class;
            }
        }

        if ($triggered !== []) {
            $entry->triggered_watches = $triggered;
            $entry->save();
        }
    }

    /**
     * Run one watch end to end (its condition, its message and the event
     * dispatch) and report whether it fired. The whole path is guarded: a
     * watch that throws anywhere (watchFor(), message(), or a listener on the
     * dispatched event) must not break the logging call that triggered it, but
     * it must not vanish either, so the failure is handed to the application's
     * exception handler and the watch is treated as not fired.
     */
    private function evaluate(LogWatch $watch, LogEntry $entry): bool
    {
        try {
            if (! $watch->watchFor($entry)) {
                return false;
            }

            $this->events->dispatch(new LogWatchTriggered($watch, $entry, $watch->message($entry)));

            return true;
        } catch (Throwable $e) {
            $this->report($e);

            return false;
        }
    }

    /**
     * Hand a watch failure to the application's exception handler without ever
     * letting the report itself break logging. report() resolves and calls the
     * bound handler, which can be missing or can throw in turn; either way the
     * failure is swallowed here so the logging call that triggered the watch
     * always completes.
     */
    private function report(Throwable $e): void
    {
        try {
            report($e);
        } catch (Throwable) {
            // The reporter itself failed. There is nowhere left to send this
            // without risking the log write we promised never to break, so it
            // is swallowed.
        }
    }

    /**
     * @return array<class-string<LogWatch>, LogWatch>
     */
    private function watchesForContainer(string $containerKey): array
    {
        $applicable = [];

        /** @var array<class-string<LogWatch>, array<string, mixed>> $configured */
        $configured = $this->config->get('log-containers.watches', []);

        foreach ($configured as $class => $options) {
            if (($options['enabled'] ?? true) === false) {
                continue;
            }

            /** @var array<int, string> $containers */
            $containers = $options['containers'] ?? ['*'];

            if (in_array('*', $containers, true) || in_array($containerKey, $containers, true)) {
                $applicable[$class] = $this->resolve($class);
            }
        }

        return $applicable;
    }

    /**
     * @param  class-string<LogWatch>  $class
     */
    private function resolve(string $class): LogWatch
    {
        return $this->instances[$class] ??= $this->app->make($class);
    }
}
