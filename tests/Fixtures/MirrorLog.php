<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;

class MirrorLog extends LogContainer
{
    public static function key(): string
    {
        return 'mirror';
    }

    public static function name(): string
    {
        return 'Mirror';
    }

    public function mirrorsToLaravelLog(): bool
    {
        return true;
    }
}
