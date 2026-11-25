<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures\Watches;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Watches\LogWatch;
use RuntimeException;

/**
 * A watch that matches but throws while building its message, to prove the
 * whole watch path, not just watchFor(), is guarded so the write survives.
 */
class ThrowingMessageWatch extends LogWatch
{
    public function watchFor(LogEntry $entry): bool
    {
        return true;
    }

    public function message(LogEntry $entry): string
    {
        throw new RuntimeException('message is broken');
    }
}
