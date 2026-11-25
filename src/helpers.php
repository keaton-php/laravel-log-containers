<?php

declare(strict_types=1);

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\LogContainerManager;

if (! function_exists('log_container')) {
    /**
     * Resolve a log container by its class name or key().
     *
     * @param  class-string<LogContainer>|string  $containerOrKey
     */
    function log_container(string $containerOrKey): LogContainer
    {
        return app(LogContainerManager::class)->container($containerOrKey);
    }
}
