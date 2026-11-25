<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures\Watches;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Watches\LogWatch;

class WidgetErrorWatch extends LogWatch
{
    public function name(): string
    {
        return 'Widget error';
    }

    public function watchFor(LogEntry $entry): bool
    {
        return $entry->level === 'error';
    }

    public function message(LogEntry $entry): string
    {
        return 'error: '.$entry->message;
    }
}
