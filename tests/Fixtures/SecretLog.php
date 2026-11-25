<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;

class SecretLog extends LogContainer
{
    public static function key(): string
    {
        return 'secrets';
    }

    public static function name(): string
    {
        return 'Secrets';
    }

    public function redactKeys(): array
    {
        return ['password'];
    }

    public function redactPatterns(): array
    {
        return ['/\d{3}-\d{2}-\d{4}/'];
    }
}
