<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Writing;

/**
 * The scope a container is in when it writes: the labels, session, context and
 * sticky tags the entry should carry.
 *
 * @internal
 */
final class Scope
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array{type: string|null, id: string|null, label: string}>  $tags
     */
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $doing = null,
        public readonly ?string $during = null,
        public readonly ?int $sessionId = null,
        public readonly array $context = [],
        public readonly array $tags = [],
    ) {}
}
