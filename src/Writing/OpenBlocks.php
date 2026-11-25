<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Writing;

use Keaton\LogContainers\LogContainer;

/**
 * The doing() and during() blocks that are running right now, innermost last.
 * The manager reads it so a container resolved inside a block starts with that
 * block's scope, and a new session reads it to find its parent.
 *
 * @internal
 */
final class OpenBlocks
{
    /**
     * @var list<array{LogContainer, int|null}>
     */
    private array $blocks = [];

    public function enter(LogContainer $container, ?int $sessionId = null): void
    {
        $this->blocks[] = [$container, $sessionId];
    }

    public function leave(): void
    {
        array_pop($this->blocks);
    }

    /**
     * The container of the innermost open block of this class, if there is one.
     *
     * @param  class-string<LogContainer>  $class
     */
    public function current(string $class): ?LogContainer
    {
        foreach (array_reverse($this->blocks) as [$container]) {
            if ($container::class === $class) {
                return $container;
            }
        }

        return null;
    }

    /**
     * The session of the innermost open doing() block, whichever container
     * opened it.
     */
    public function sessionId(): ?int
    {
        foreach (array_reverse($this->blocks) as [, $sessionId]) {
            if ($sessionId !== null) {
                return $sessionId;
            }
        }

        return null;
    }
}
