<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;

/**
 * A container whose before hook is protected. Hooks are called through the
 * service container, so a hook has to be public.
 */
class ProtectedHookLog extends LogContainer
{
    public static function key(): string
    {
        return 'protected-hook';
    }

    public static function name(): string
    {
        return 'Protected hook';
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function beforeLog(array $attributes): array
    {
        return $attributes;
    }
}
