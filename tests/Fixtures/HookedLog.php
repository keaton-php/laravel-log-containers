<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;

class HookedLog extends LogContainer
{
    public static function key(): string
    {
        return 'hooked';
    }

    public static function name(): string
    {
        return 'Hooked';
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function beforeLog(array $attributes): array
    {
        $context = is_array($attributes['context'] ?? null) ? $attributes['context'] : [];
        $attributes['context'] = array_merge($context, ['hooked' => true]);

        return $attributes;
    }

    /**
     * Drop every debug entry before it is written.
     */
    public function beforeDebug(): bool
    {
        return false;
    }

    /**
     * Resolved through the container: the Recorder is injected.
     */
    public function onLog(LogEntry $entry, Recorder $recorder): void
    {
        $recorder->record($entry->id);
    }
}
