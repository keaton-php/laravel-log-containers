<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;
use RuntimeException;

/**
 * A container whose onException() after-hook throws, so writing the exception
 * entry for a failed doing() or during() block itself fails. This is the case
 * that must not be allowed to replace the block's original exception: the docs
 * promise it is re-thrown unchanged.
 */
class FailingExceptionLog extends LogContainer
{
    public static function key(): string
    {
        return 'failing-exception';
    }

    public static function name(): string
    {
        return 'Failing exception';
    }

    public function onException(LogEntry $entry): void
    {
        throw new RuntimeException('logging the exception itself failed');
    }
}
