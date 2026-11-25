<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

/**
 * A plain service the test binds as a singleton, so a hook that type-hints it
 * proves the container injects into hooks.
 */
class Recorder
{
    /**
     * @var array<int, int>
     */
    public array $ids = [];

    public function record(int $id): void
    {
        $this->ids[] = $id;
    }
}
