<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;

class RetiringLog extends LogContainer
{
    public static function key(): string
    {
        return 'retiring';
    }

    public static function name(): string
    {
        return 'Retiring';
    }

    public function retentionDays(): ?int
    {
        return 7;
    }
}
