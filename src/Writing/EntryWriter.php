<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Writing;

use Illuminate\Support\Facades\Log;
use Keaton\LogContainers\ExecutionContext;
use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\LogContainerManager;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogTag;
use Throwable;

/**
 * The write path. Every entry a container stores goes through write().
 *
 * @internal
 */
final class EntryWriter
{
    public function __construct(
        private LogContainerManager $manager,
        private ExecutionContext $execution,
        private ContainerSettings $settings,
        private ContextPreparer $context,
        private HookRunner $hooks,
        private SessionRecorder $sessions,
        private Trimmer $trimmer,
    ) {}

    /**
     * Store one entry. When the container or the level is off, or a before
     * hook returns false, nothing is stored and an unsaved LogEntry comes back.
     *
     * @param  array<string, mixed>  $context
     */
    public function write(
        LogContainer $container,
        Scope $scope,
        string $level,
        string $message,
        array $context = [],
        ?Throwable $exception = null,
    ): LogEntry {
        if (! $this->settings->enabled($container) || ! $this->settings->allows($container, $level)) {
            return new LogEntry;
        }

        $context = $this->context->prepare($container, $scope->context, $context);

        $attributes = [
            'container_key' => $container::key(),
            'level' => $level,
            'message' => $message,
            'type' => $scope->type ?? $this->execution->type(),
            'doing' => $scope->doing,
            'during' => $scope->during,
            'session_id' => $scope->sessionId,
            'execution_id' => $this->execution->id(),
            'context' => $context ?: null,
            'exception_class' => $exception !== null ? $exception::class : null,
            'exception_message' => $exception?->getMessage(),
            'exception_file' => $exception?->getFile(),
            'exception_line' => $exception?->getLine(),
            'exception_trace' => $exception?->getTraceAsString(),
            'user_id' => $this->currentUserId(),
            'environment' => app()->environment(),
        ];

        if (($attributes = $this->hooks->before($container, $level, $attributes)) === false) {
            return new LogEntry;
        }

        /** @var LogEntry $entry */
        $entry = LogEntry::query()->create($attributes);

        $this->attachTags($entry, $scope);
        $this->sessions->count($entry);
        $this->mirror($container, $entry);
        $this->hooks->after($container, $level, $entry);
        $this->manager->watches()->inspect($entry);
        $this->trimmer->maybeTrim($container);

        return $entry;
    }

    private function attachTags(LogEntry $entry, Scope $scope): void
    {
        foreach ($scope->tags as $tag) {
            LogTag::query()->create([
                'log_entry_id' => $entry->id,
                'taggable_type' => $tag['type'],
                'taggable_id' => $tag['id'],
                'label' => $tag['label'],
            ]);
        }
    }

    private function mirror(LogContainer $container, LogEntry $entry): void
    {
        if (! $container->mirrorsToLaravelLog() || $this->manager->isFaked()) {
            return;
        }

        $level = match ($entry->level) {
            'exception' => 'error',
            'success' => 'info',
            default => $entry->level,
        };

        Log::channel($container->laravelLogChannel())->log($level, $entry->message, $entry->context ?? []);
    }

    private function currentUserId(): ?string
    {
        // No auth service at all (a bare package or console context) means no
        // user. A guard that is bound but misconfigured is a real bug and is
        // allowed to surface.
        if (! function_exists('auth') || ! app()->bound('auth')) {
            return null;
        }

        $id = auth()->id();

        return $id === null ? null : (string) $id;
    }
}
