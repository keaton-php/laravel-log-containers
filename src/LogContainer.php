<?php

declare(strict_types=1);

namespace Keaton\LogContainers;

use BadMethodCallException;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogSession;
use Keaton\LogContainers\Support\LevelArguments;
use Keaton\LogContainers\Support\Tag;
use Keaton\LogContainers\Writing\EntryWriter;
use Keaton\LogContainers\Writing\OpenBlocks;
use Keaton\LogContainers\Writing\Scope;
use Keaton\LogContainers\Writing\SessionRecorder;
use Throwable;

/**
 * A named log container. Extend it, give it a key and a name, and override the
 * declarations you need (allowed levels, retention, redaction). Every entry
 * written to it is a row on its channel.
 *
 *   class OrderLog extends LogContainer
 *   {
 *       public static function key(): string  { return 'orders'; }
 *       public static function name(): string { return 'Orders'; }
 *   }
 *
 *   OrderLog::info('Order placed', ['id' => 42]);
 *   log_container(OrderLog::class)->info('Order placed', ['id' => 42]);
 *
 * Both forms resolve the container through the service container, so the
 * application has to be booted. Use log_container() when you want an instance
 * to hold on to. Called inside a doing() or during() block of the same
 * container, both forms join that block.
 *
 * PHP doesn't allow info() to be both an instance and a static method, so the
 * level, scope and fluent methods are served by __call() and __callStatic().
 * The tags below list them for IDEs and PHPStan.
 *
 * This class holds what a container is: its declarations, its scope and the
 * magic methods. Writing an entry is done by Writing\EntryWriter. DECISIONS.md
 * explains the split and what a subclass can rely on.
 *
 * @method LogEntry debug(string $message, array<string, mixed> $context = [])
 * @method LogEntry info(string $message, array<string, mixed> $context = [])
 * @method LogEntry notice(string $message, array<string, mixed> $context = [])
 * @method LogEntry warning(string $message, array<string, mixed> $context = [])
 * @method LogEntry error(string $message, array<string, mixed> $context = [])
 * @method LogEntry critical(string $message, array<string, mixed> $context = [])
 * @method LogEntry alert(string $message, array<string, mixed> $context = [])
 * @method LogEntry emergency(string $message, array<string, mixed> $context = [])
 * @method LogEntry success(string $message, array<string, mixed> $context = [])
 * @method static LogEntry debug(string $message, array<string, mixed> $context = [])
 * @method static LogEntry info(string $message, array<string, mixed> $context = [])
 * @method static LogEntry notice(string $message, array<string, mixed> $context = [])
 * @method static LogEntry warning(string $message, array<string, mixed> $context = [])
 * @method static LogEntry error(string $message, array<string, mixed> $context = [])
 * @method static LogEntry critical(string $message, array<string, mixed> $context = [])
 * @method static LogEntry alert(string $message, array<string, mixed> $context = [])
 * @method static LogEntry emergency(string $message, array<string, mixed> $context = [])
 * @method static LogEntry success(string $message, array<string, mixed> $context = [])
 * @method LogEntry exception(string $message, \Throwable $exception, array<string, mixed> $context = [])
 * @method $this type(string $type)
 * @method $this context(array<string, mixed> $context)
 * @method $this tagWith(\Illuminate\Database\Eloquent\Model|string $tag)
 * @method $this clearTags()
 * @method mixed doing(string $label, ?\Closure $callback = null)
 * @method mixed during(string $label, ?\Closure $callback = null)
 * @method static LogEntry exception(string $message, \Throwable $exception, array<string, mixed> $context = [])
 * @method static static type(string $type)
 * @method static static context(array<string, mixed> $context)
 * @method static static tagWith(\Illuminate\Database\Eloquent\Model|string $tag)
 * @method static static clearTags()
 * @method static mixed doing(string $label, ?\Closure $callback = null)
 * @method static mixed during(string $label, ?\Closure $callback = null)
 */
abstract class LogContainer
{
    /**
     * The levels, in order of increasing severity. "exception" and "success"
     * are additions to the PSR-3 set.
     */
    public const LEVELS = [
        'debug', 'info', 'notice', 'warning', 'error',
        'critical', 'alert', 'emergency', 'exception', 'success',
    ];

    /**
     * The level methods that take a message and a context.
     */
    private const SIMPLE_LEVELS = [
        'debug', 'info', 'notice', 'warning', 'error',
        'critical', 'alert', 'emergency', 'success',
    ];

    /**
     * The other magic methods, mapped to the private method each one calls.
     *
     * @var array<string, string>
     */
    private const FORWARDED = [
        'exception' => 'writeException',
        'doing' => 'startDoing',
        'during' => 'startDuring',
        'tagWith' => 'applyTag',
        'context' => 'applyContext',
        'type' => 'applyType',
        'clearTags' => 'applyClearTags',
    ];

    private ?string $doing = null;

    private ?string $during = null;

    private ?string $type = null;

    /**
     * @var array<string, mixed>
     */
    private array $context = [];

    private ?int $sessionId = null;

    /**
     * Sticky tags, applied to every entry until cleared or a scope ends.
     *
     * @var array<int, array{type: string|null, id: string|null, label: string}>
     */
    private array $tags = [];

    /**
     * A unique key for this container; the channel its entries are stored on.
     */
    abstract public static function key(): string;

    /**
     * A human name for the container.
     */
    abstract public static function name(): string;

    // ------------------------------------------------------------------
    // Declarations a subclass may override

    /**
     * The levels this container accepts, or null for all of them. An entry at
     * any other level is dropped before it is written.
     *
     * @return array<int, string>|null
     */
    public function allowedLevels(): ?array
    {
        return null;
    }

    /**
     * How many days of entries to keep. The prune command deletes anything
     * older. Null keeps entries until a max-entries trim removes them.
     */
    public function retentionDays(): ?int
    {
        return null;
    }

    /**
     * The most entries to keep for this container. Older rows are trimmed away
     * as new ones are written. Null means no cap.
     */
    public function maxEntries(): ?int
    {
        return null;
    }

    /**
     * Context keys whose values are replaced with "[redacted]" before storage.
     *
     * @return array<int, string>
     */
    public function redactKeys(): array
    {
        return [];
    }

    /**
     * Regexes; a match inside a string context value is redacted before
     * storage. Use for secrets that travel in free text.
     *
     * @return array<int, string>
     */
    public function redactPatterns(): array
    {
        return [];
    }

    /**
     * Context merged into every entry this container writes.
     *
     * @return array<string, mixed>
     */
    public function defaultContext(): array
    {
        return [];
    }

    /**
     * Whether entries are also written to the standard Laravel log. Defaults to
     * the package config value.
     */
    public function mirrorsToLaravelLog(): bool
    {
        return (bool) app(Repository::class)->get('log-containers.mirror_to_laravel_log', false);
    }

    /**
     * The Laravel log channel to mirror to, or null for the default stack.
     */
    public function laravelLogChannel(): ?string
    {
        $channel = app(Repository::class)->get('log-containers.laravel_log_channel');

        return is_string($channel) ? $channel : null;
    }

    // ------------------------------------------------------------------
    // Scope: type(), context(), tagWith(), clearTags(), doing() and during()

    /**
     * The sticky tags currently set on this container.
     *
     * @return array<int, array{type: string|null, id: string|null, label: string}>
     */
    final public function tags(): array
    {
        return $this->tags;
    }

    /**
     * Open a session row by hand. doing() opens and closes one for you. Inside
     * a doing() block the new session is a child of that block's session.
     *
     * @param  array<string, mixed>  $context
     */
    final public function startSession(?string $name = null, array $context = []): LogSession
    {
        return app(SessionRecorder::class)->open($this, $this->context, $name, $context, app(OpenBlocks::class)->sessionId());
    }

    private function applyType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function applyContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Tag every entry from here on with a model (a polymorphic link you can
     * query back with LogEntry::taggedWith($model)) or a plain string label.
     * Tags are sticky: they stay until clearTags(), or until the doing() or
     * during() block they were set in ends.
     */
    private function applyTag(Model|string $tag): static
    {
        $tag = Tag::from($tag);

        if (! in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    private function applyClearTags(): static
    {
        $this->tags = [];

        return $this;
    }

    /**
     * Run a block as a timed session. Every entry written inside it is stamped
     * with the label and linked to a session row that records the duration and
     * whether the block finished or threw. An exception is logged against the
     * container and re-thrown.
     *
     * A block that starts while another doing() block is running is a child
     * session of it, whichever container opened the outer one. While the block
     * runs, a static call or log_container() for this container joins it.
     *
     * Without a callback it only sets the label and returns the container.
     *
     * @template TReturn
     *
     * @param  (Closure(static): TReturn)|null  $callback
     * @return ($callback is null ? static : TReturn)
     */
    private function startDoing(string $label, ?Closure $callback = null): mixed
    {
        if ($callback === null) {
            $this->doing = $label;

            return $this;
        }

        $previous = [$this->doing, $this->sessionId, $this->tags];
        $this->doing = $label;

        $sessions = app(SessionRecorder::class);
        $blocks = app(OpenBlocks::class);
        $session = $sessions->openIfEnabled($this, $this->context, $label, $blocks->sessionId());
        $this->sessionId = $session?->id;

        $blocks->enter($this, $session?->id);

        $startedAt = microtime(true);
        $failed = false;

        try {
            return $callback($this);
        } catch (Throwable $e) {
            $failed = true;

            try {
                $this->writeException('Unhandled exception in doing block', $e, ['doing' => $label]);
            } catch (Throwable) {
                // A failed log write must never replace the exception the block threw.
            }

            throw $e;
        } finally {
            $blocks->leave();

            if ($session !== null) {
                $sessions->close($session, $startedAt, $failed);
            }

            [$this->doing, $this->sessionId, $this->tags] = $previous;
        }
    }

    /**
     * Stamp every entry written inside the block with a scope label, without
     * opening a session row. An exception is logged and re-thrown.
     *
     * Without a callback it only sets the label and returns the container.
     *
     * @template TReturn
     *
     * @param  (Closure(static): TReturn)|null  $callback
     * @return ($callback is null ? static : TReturn)
     */
    private function startDuring(string $label, ?Closure $callback = null): mixed
    {
        if ($callback === null) {
            $this->during = $label;

            return $this;
        }

        $previous = [$this->during, $this->tags];
        $this->during = $label;

        $blocks = app(OpenBlocks::class);
        $blocks->enter($this);

        try {
            return $callback($this);
        } catch (Throwable $e) {
            try {
                $this->writeException('Unhandled exception in during block', $e, ['during' => $label]);
            } catch (Throwable) {
                // As in doing(): a failed log write must not replace the block's exception.
            }

            throw $e;
        } finally {
            $blocks->leave();

            [$this->during, $this->tags] = $previous;
        }
    }

    // ------------------------------------------------------------------
    // Magic methods

    /**
     * The instance form, e.g. $log->info('...', [...]) or $log->doing(...).
     * Any name outside the magic methods throws.
     *
     * @param  array<array-key, mixed>  $arguments
     */
    final public function __call(string $name, array $arguments): mixed
    {
        if (in_array($name, self::SIMPLE_LEVELS, true)) {
            [$message, $context] = LevelArguments::parse($name, $arguments);

            return $this->write($name, $message, $context);
        }

        if (isset(self::FORWARDED[$name])) {
            return $this->{self::FORWARDED[$name]}(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method %s::%s().', static::class, $name));
    }

    /**
     * The static form, e.g. OrderLog::info('...', [...]). It resolves the same
     * instance log_container() would return and calls the method on it, so it
     * joins a block of this container that is open.
     *
     * The name is checked before anything is resolved, so a static call can
     * never reach write() or a private method.
     *
     * @param  array<array-key, mixed>  $arguments
     */
    final public static function __callStatic(string $name, array $arguments): mixed
    {
        if (! in_array($name, self::SIMPLE_LEVELS, true) && ! isset(self::FORWARDED[$name])) {
            throw new BadMethodCallException(sprintf('Call to undefined method %s::%s().', static::class, $name));
        }

        return app(LogContainerManager::class)->container(static::class)->{$name}(...$arguments);
    }

    // ------------------------------------------------------------------
    // Writing

    /**
     * Write one entry. Every level method ends up here, so this is the place
     * for a subclass to wrap a write, or to call from a method of its own.
     *
     * @param  array<string, mixed>  $context
     */
    protected function write(string $level, string $message, array $context = [], ?Throwable $exception = null): LogEntry
    {
        $scope = new Scope($this->type, $this->doing, $this->during, $this->sessionId, $this->context, $this->tags);

        return app(EntryWriter::class)->write($this, $scope, $level, $message, $context, $exception);
    }

    /**
     * The exception() level: store a throwable's class, message and location
     * with the entry.
     *
     * @param  array<string, mixed>  $context
     */
    private function writeException(string $message, Throwable $exception, array $context = []): LogEntry
    {
        return $this->write('exception', $message, $context, $exception);
    }
}
