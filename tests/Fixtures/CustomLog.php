<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;
use Throwable;

/**
 * A container that uses what a subclass is allowed to use: a constructor of
 * its own, a writing method of its own, and an override of write().
 */
class CustomLog extends LogContainer
{
    public function __construct(
        private Recorder $recorder,
    ) {}

    public static function key(): string
    {
        return 'custom';
    }

    public static function name(): string
    {
        return 'Custom';
    }

    public function audit(string $message): LogEntry
    {
        return $this->write('notice', $message, ['audit' => true]);
    }

    /**
     * A method with the name of one of the base class's private methods.
     */
    public function applyTag(): string
    {
        return 'not the base method';
    }

    protected function write(string $level, string $message, array $context = [], ?Throwable $exception = null): LogEntry
    {
        $entry = parent::write($level, strtoupper($message), $context, $exception);

        $this->recorder->record($entry->id);

        return $entry;
    }
}
