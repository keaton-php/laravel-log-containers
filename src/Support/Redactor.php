<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Support;

use InvalidArgumentException;

/**
 * Removes secrets from a context array before it is stored: whole values by
 * key, and matched substrings by pattern.
 *
 * @internal
 */
class Redactor
{
    private const PLACEHOLDER = '[redacted]';

    /**
     * @param  array<int, string>  $keys  context keys whose value is replaced wholesale
     * @param  array<int, string>  $patterns  regexes; a match inside a string value is replaced
     */
    public function __construct(
        private array $keys,
        private array $patterns,
    ) {}

    public function hasRules(): bool
    {
        return $this->keys !== [] || $this->patterns !== [];
    }

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    public function redact(array $context): array
    {
        if (! $this->hasRules()) {
            return $context;
        }

        $out = [];

        foreach ($context as $key => $value) {
            if (in_array((string) $key, $this->keys, true)) {
                $out[$key] = self::PLACEHOLDER;

                continue;
            }

            $out[$key] = is_array($value) ? $this->redact($value) : $this->scrub($value);
        }

        return $out;
    }

    private function scrub(mixed $value): mixed
    {
        if (! is_string($value) || $this->patterns === []) {
            return $value;
        }

        foreach ($this->patterns as $pattern) {
            $replaced = preg_replace($pattern, self::PLACEHOLDER, $value);

            // A null return means the pattern is invalid. Silently keeping the
            // original string would persist the very secret the pattern was
            // meant to remove, so refuse loudly instead.
            if ($replaced === null) {
                $reason = preg_last_error() !== PREG_NO_ERROR
                    ? preg_last_error_msg()
                    : 'the pattern is not a valid regular expression';

                throw new InvalidArgumentException(sprintf(
                    'Invalid redaction pattern [%s]: %s.',
                    $pattern,
                    $reason,
                ));
            }

            $value = $replaced;
        }

        return $value;
    }
}
