<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures\Watches;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Watches\LogWatch;
use RuntimeException;

/**
 * A watch whose check throws, to prove a broken watch cannot break the write.
 */
class BrokenWatch extends LogWatch
{
    public function watchFor(LogEntry $entry): bool
    {
        throw new RuntimeException('watch is broken');
    }
}
