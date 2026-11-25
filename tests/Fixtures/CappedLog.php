<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;

class CappedLog extends LogContainer
{
    public static function key(): string
    {
        return 'capped';
    }

    public static function name(): string
    {
        return 'Capped';
    }

    public function maxEntries(): ?int
    {
        return 3;
    }
}
