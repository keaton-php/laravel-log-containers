<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;

/**
 * A container whose after hook does not call its parameter $entry.
 */
class RenamedHookLog extends LogContainer
{
    public static function key(): string
    {
        return 'renamed-hook';
    }

    public static function name(): string
    {
        return 'Renamed hook';
    }

    public function onLog(Recorder $recorder, LogEntry $written): void
    {
        $recorder->record($written->id);
    }
}
