<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;

/**
 * A container whose after hook for one level is private.
 */
class PrivateHookLog extends LogContainer
{
    public static function key(): string
    {
        return 'private-hook';
    }

    public static function name(): string
    {
        return 'Private hook';
    }

    private function onError(LogEntry $entry): void
    {
        //
    }
}
