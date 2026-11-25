<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;

class ProblemsLog extends LogContainer
{
    public static function key(): string
    {
        return 'problems';
    }

    public static function name(): string
    {
        return 'Problems';
    }

    public function allowedLevels(): ?array
    {
        return ['error', 'critical'];
    }
}
