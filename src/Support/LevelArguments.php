<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Support;

use InvalidArgumentException;

/**
 * Checks the arguments of a magic level call such as info('message', [...]).
 *
 * __call() and __callStatic() bypass PHP's own type checks, so a wrong-typed
 * or unexpected argument is refused here instead of being stored as a broken
 * row.
 *
 * @internal
 */
final class LevelArguments
{
    /**
     * Pull the (string $message, array $context = []) pair out of the
     * arguments. PHP passes named arguments to __call() as string keys, so
     * info('x') and info(message: 'x', context: [...]) both work.
     *
     * @param  array<array-key, mixed>  $arguments
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function parse(string $method, array $arguments): array
    {
        $unexpected = array_diff(array_keys($arguments), [0, 1, 'message', 'context']);

        if ($unexpected !== []) {
            throw new InvalidArgumentException(sprintf(
                'Log level [%s] received unexpected argument(s) [%s]; it accepts a string message and an array context only.',
                $method,
                implode(', ', array_map(static fn (int|string $key): string => (string) $key, $unexpected)),
            ));
        }

        // info('first', message: 'second') is ambiguous. PHP rejects it for a
        // real method, so it is rejected here too.
        foreach ([0 => 'message', 1 => 'context'] as $position => $named) {
            if (array_key_exists($position, $arguments) && array_key_exists($named, $arguments)) {
                throw new InvalidArgumentException(sprintf(
                    'Log level [%s] received argument [%s] both positionally and by name; pass it once.',
                    $method,
                    $named,
                ));
            }
        }

        $message = $arguments[0] ?? $arguments['message'] ?? null;

        if (! is_string($message)) {
            throw new InvalidArgumentException(sprintf(
                'Log level [%s] expects a string message as its first argument, got %s.',
                $method,
                get_debug_type($message),
            ));
        }

        $context = $arguments[1] ?? $arguments['context'] ?? [];

        if (! is_array($context)) {
            throw new InvalidArgumentException(sprintf(
                'Log level [%s] expects an array context as its second argument, got %s.',
                $method,
                get_debug_type($context),
            ));
        }

        /** @var array<string, mixed> $typedContext */
        $typedContext = $context;

        return [$message, $typedContext];
    }
}
