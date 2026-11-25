<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Writing;

use Illuminate\Support\Carbon;
use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogSession;

/**
 * Opens, counts and closes the session rows behind doing().
 *
 * @internal
 */
final class SessionRecorder
{
    private const ERROR_LEVELS = ['error', 'critical', 'alert', 'emergency', 'exception'];

    public function __construct(
        private ContainerSettings $settings,
        private ContextPreparer $context,
    ) {}

    /**
     * @param  array<string, mixed>  $scoped
     * @param  array<string, mixed>  $context
     */
    public function open(LogContainer $container, array $scoped, ?string $name = null, array $context = [], ?int $parentId = null): LogSession
    {
        return LogSession::query()->create([
            'container_key' => $container::key(),
            'parent_id' => $parentId,
            'name' => $name,
            'status' => LogSession::STATUS_OPEN,
            'context' => $this->context->prepare($container, $scoped, $context) ?: null,
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * Open a session for a doing() block, unless the container is switched off.
     * The parent is the session of the doing() block it runs inside.
     *
     * @param  array<string, mixed>  $scoped
     */
    public function openIfEnabled(LogContainer $container, array $scoped, string $name, ?int $parentId = null): ?LogSession
    {
        return $this->settings->enabled($container) ? $this->open($container, $scoped, $name, [], $parentId) : null;
    }

    /**
     * Count an entry against the session it was written in.
     */
    public function count(LogEntry $entry): void
    {
        if ($entry->session_id === null) {
            return;
        }

        $query = LogSession::query()->whereKey($entry->session_id);
        $query->increment('entry_count');

        if (in_array($entry->level, self::ERROR_LEVELS, true)) {
            $query->increment('error_count');
        }
    }

    public function close(LogSession $session, float $startedAt, bool $failed): void
    {
        $session->forceFill([
            'status' => $failed ? LogSession::STATUS_FAILED : LogSession::STATUS_COMPLETED,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ended_at' => Carbon::now(),
        ])->save();
    }
}
