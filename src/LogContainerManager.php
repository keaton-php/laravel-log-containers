<?php

declare(strict_types=1);

namespace Keaton\LogContainers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Watches\WatchRegistry;
use Keaton\LogContainers\Writing\OpenBlocks;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * The entry point to the package, resolved behind the LogContainers facade.
 * Hands back container instances, holds the test fake, and carries the
 * assertions that read the stored entries back.
 */
class LogContainerManager
{
    /**
     * Known container classes keyed by their key().
     *
     * @var array<string, class-string<LogContainer>>
     */
    private array $containers = [];

    private bool $faked = false;

    private int $fakeFloorId = 0;

    public function __construct(
        private Container $app,
        private WatchRegistry $watches,
    ) {}

    /**
     * Resolve a container by its class name or its key(). A new instance is
     * returned each call. Inside a doing() or during() block of that container
     * it starts with the block's labels, session, context and tags. Anywhere
     * else it starts clean.
     *
     * @param  class-string<LogContainer>|string  $containerOrKey
     */
    public function container(string $containerOrKey): LogContainer
    {
        $class = $this->resolveClass($containerOrKey);

        $this->containers[$class::key()] = $class;

        $open = $this->app->make(OpenBlocks::class)->current($class);

        if ($open !== null) {
            return clone $open;
        }

        /** @var LogContainer $container */
        $container = $this->app->make($class);

        return $container;
    }

    public function watches(): WatchRegistry
    {
        return $this->watches;
    }

    /**
     * Start faking: later assertions only consider entries written from now on,
     * and mirroring to the Laravel log is suppressed. Entries are still stored
     * (on the test database), which is what the assertions read.
     */
    public function fake(): static
    {
        $this->faked = true;
        $this->fakeFloorId = (int) (LogEntry::query()->max('id') ?? 0);

        return $this;
    }

    public function isFaked(): bool
    {
        return $this->faked;
    }

    public function assertLogged(string $containerOrKey, ?string $level = null): void
    {
        $key = $this->keyFor($containerOrKey);

        PHPUnit::assertTrue(
            $this->query($key, $level)->exists(),
            sprintf('Failed asserting that an entry in container [%s]%s was recorded.', $key, $this->levelClause($level)),
        );
    }

    public function assertNotLogged(string $containerOrKey, ?string $level = null): void
    {
        $key = $this->keyFor($containerOrKey);

        PHPUnit::assertFalse(
            $this->query($key, $level)->exists(),
            sprintf('Failed asserting that no entry in container [%s]%s was recorded.', $key, $this->levelClause($level)),
        );
    }

    public function assertCount(string $containerOrKey, int $expected, ?string $level = null): void
    {
        $key = $this->keyFor($containerOrKey);
        $actual = $this->query($key, $level)->count();

        PHPUnit::assertSame(
            $expected,
            $actual,
            sprintf('Failed asserting that %d entries were recorded in container [%s]%s. %d found.', $expected, $key, $this->levelClause($level), $actual),
        );
    }

    /**
     * @param  class-string<LogContainer>|string  $containerOrKey
     * @return class-string<LogContainer>
     */
    private function resolveClass(string $containerOrKey): string
    {
        if (is_subclass_of($containerOrKey, LogContainer::class)) {
            return $containerOrKey;
        }

        if (isset($this->containers[$containerOrKey])) {
            return $this->containers[$containerOrKey];
        }

        $class = $this->discoverByKey($containerOrKey);

        if ($class === null) {
            throw new InvalidArgumentException("Log container [{$containerOrKey}] is not a registered container class or key.");
        }

        return $class;
    }

    /**
     * Find a container class whose key() matches, among those named in config.
     *
     * @return class-string<LogContainer>|null
     */
    private function discoverByKey(string $key): ?string
    {
        /** @var array<class-string<LogContainer>, mixed> $configured */
        $configured = $this->app->make('config')->get('log-containers.containers', []);

        foreach (array_keys($configured) as $class) {
            if (is_subclass_of($class, LogContainer::class) && $class::key() === $key) {
                $this->containers[$key] = $class;

                return $class;
            }
        }

        return null;
    }

    private function keyFor(string $containerOrKey): string
    {
        return is_subclass_of($containerOrKey, LogContainer::class)
            ? $containerOrKey::key()
            : $containerOrKey;
    }

    /**
     * @return Builder<LogEntry>
     */
    private function query(string $key, ?string $level): Builder
    {
        $query = LogEntry::query()->where('container_key', $key);

        if ($level !== null) {
            $query->where('level', $level);
        }

        if ($this->faked) {
            $query->where('id', '>', $this->fakeFloorId);
        }

        return $query;
    }

    private function levelClause(?string $level): string
    {
        return $level !== null ? " at level [{$level}]" : '';
    }
}
