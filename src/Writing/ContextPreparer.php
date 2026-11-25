<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Writing;

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Support\ContextSanitizer;
use Keaton\LogContainers\Support\Redactor;

/**
 * Builds the context that is stored with an entry or a session.
 *
 * @internal
 */
final class ContextPreparer
{
    /**
     * Merge the container's default context, the scoped context and the
     * context of this call, make the result JSON-safe, then redact it.
     *
     * Redaction has to run last. Sanitizing turns objects into arrays and
     * strings, which can surface a secret key or a secret-bearing string that
     * was hidden inside the object.
     *
     * @param  array<string, mixed>  $scoped
     * @param  array<string, mixed>  $extra
     * @return array<array-key, mixed>
     */
    public function prepare(LogContainer $container, array $scoped, array $extra = []): array
    {
        $context = array_merge($container->defaultContext(), $scoped, $extra);
        $context = (new ContextSanitizer)->sanitize($context);

        return (new Redactor($container->redactKeys(), $container->redactPatterns()))->redact($context);
    }
}
