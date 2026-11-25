<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Support;

use BackedEnum;
use Closure;
use DateTimeInterface;
use JsonSerializable;
use Throwable;
use UnitEnum;

/**
 * Makes an arbitrary context array safe to store as JSON, so a value a caller
 * passed can never make an entry fail to encode and lose the whole log line.
 *
 * The raw value a caller passed is never mutated; this only runs on the copy
 * that is persisted.
 *
 * @internal
 */
class ContextSanitizer
{
    private const MAX_DEPTH = 16;

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    public function sanitize(array $context): array
    {
        /** @var array<array-key, mixed> $clean */
        $clean = $this->value($context, 0, []);

        return $clean;
    }

    /**
     * @param  array<int, object>  $seen
     */
    private function value(mixed $value, int $depth, array $seen): mixed
    {
        if ($depth >= self::MAX_DEPTH) {
            return '[max depth]';
        }

        return match (true) {
            is_scalar($value), $value === null => $this->scalar($value),
            is_array($value) => $this->traverse($value, $depth, $seen),
            $value instanceof Closure => 'Closure',
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Throwable => $value::class.': '.$value->getMessage(),
            is_object($value) => $this->object($value, $depth, $seen),
            is_resource($value) => 'resource('.get_resource_type($value).')',
            default => '[unencodable]',
        };
    }

    private function scalar(mixed $value): mixed
    {
        if (is_float($value) && ! is_finite($value)) {
            return is_nan($value) ? 'NAN' : 'INF';
        }

        if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  array<int, object>  $seen
     * @return array<array-key, mixed>
     */
    private function traverse(array $value, int $depth, array $seen): array
    {
        $out = [];

        foreach ($value as $key => $item) {
            $out[$key] = $this->value($item, $depth + 1, $seen);
        }

        return $out;
    }

    /**
     * @param  array<int, object>  $seen
     */
    private function object(object $value, int $depth, array $seen): mixed
    {
        if (in_array($value, $seen, true)) {
            return '[recursion: '.$value::class.']';
        }

        $seen[] = $value;

        // A caller's object may implement any of these conversions and throw
        // from inside them. The whole point of the sanitizer is that a value a
        // caller passed can never make an entry fail to encode, so a throwing
        // conversion is caught and replaced with a JSON-safe diagnostic rather
        // than aborting the log write.
        if ($value instanceof JsonSerializable) {
            try {
                return $this->value($value->jsonSerialize(), $depth + 1, $seen);
            } catch (Throwable $e) {
                return $this->conversionFailed($value, 'jsonSerialize', $e);
            }
        }

        if (method_exists($value, 'toArray')) {
            try {
                /** @var array<array-key, mixed> $array */
                $array = $value->toArray();
            } catch (Throwable $e) {
                return $this->conversionFailed($value, 'toArray', $e);
            }

            return $this->traverse($array, $depth, $seen);
        }

        if (method_exists($value, '__toString')) {
            try {
                return (string) $value;
            } catch (Throwable $e) {
                return $this->conversionFailed($value, '__toString', $e);
            }
        }

        $properties = get_object_vars($value);
        $properties['__class'] = $value::class;

        return $this->traverse($properties, $depth, $seen);
    }

    /**
     * A JSON-safe stand-in for an object whose conversion threw, naming the
     * class, the conversion that failed, and the thrown exception.
     *
     * @return array<string, string>
     */
    private function conversionFailed(object $value, string $via, Throwable $e): array
    {
        return [
            '__unconvertible' => $value::class,
            'via' => $via,
            'error' => $e::class.': '.$e->getMessage(),
        ];
    }
}
