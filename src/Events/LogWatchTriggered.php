<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Events;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Watches\LogWatch;

/**
 * Dispatched when a watch matches an entry. Listen for this to send an alert,
 * open a ticket or page someone, whatever the watch's severity calls for.
 */
class LogWatchTriggered
{
    public function __construct(
        public LogWatch $watch,
        public LogEntry $entry,
        public string $message,
    ) {}
}
