<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Facades;

use Illuminate\Support\Facades\Facade;
use Keaton\LogContainers\LogContainerManager;

/**
 * @method static \Keaton\LogContainers\LogContainer container(string $containerOrKey)
 * @method static \Keaton\LogContainers\LogContainerManager fake()
 * @method static bool isFaked()
 * @method static void assertLogged(string $containerOrKey, ?string $level = null)
 * @method static void assertNotLogged(string $containerOrKey, ?string $level = null)
 * @method static void assertCount(string $containerOrKey, int $expected, ?string $level = null)
 * @method static \Keaton\LogContainers\Watches\WatchRegistry watches()
 *
 * @see LogContainerManager
 */
class LogContainers extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LogContainerManager::class;
    }
}
