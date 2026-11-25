<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Watches;

use Keaton\LogContainers\Models\LogEntry;

/**
 * A code-defined watch. Subclass it, say what a matching entry looks like in
 * watchFor(), and register it in config. When an entry lands that matches, a
 * LogWatchTriggered event fires with the watch, the entry and the message.
 *
 * This is "alert on your own logs": the condition is a method you write and
 * test, not a query language or an external log processor.
 *
 *   class RepeatedFailureWatch extends LogWatch
 *   {
 *       public function name(): string
 *       {
 *           return 'Repeated order failures';
 *       }
 *
 *       public function watchFor(LogEntry $entry): bool
 *       {
 *           return $entry->level === 'error'
 *               && ($entry->context['attempt'] ?? 1) >= 3;
 *       }
 *   }
 */
abstract class LogWatch
{
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    /**
     * True if this entry should trigger the watch. The container has already
     * persisted the entry, so its id and relations are available.
     */
    abstract public function watchFor(LogEntry $entry): bool;

    /**
     * A human name for the watch, shown wherever alerts are listed.
     */
    public function name(): string
    {
        return class_basename(static::class);
    }

    /**
     * How loud a match is. Listeners route on this.
     */
    public function severity(): string
    {
        return self::SEVERITY_WARNING;
    }

    /**
     * The alert message for a given match. Override to describe what happened
     * using the entry; the default is the watch name and the entry message.
     */
    public function message(LogEntry $entry): string
    {
        return $this->name().': '.$entry->message;
    }
}
