<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogTag;

/**
 * Enforces each container's retentionDays() by deleting entries older than it.
 * Containers are read from config('log-containers.containers').
 */
class PruneLogContainersCommand extends Command
{
    protected $signature = 'log-containers:prune {--pretend : Report what would be deleted without deleting}';

    protected $description = 'Delete container entries past their retention window';

    public function handle(Container $app): int
    {
        /** @var array<class-string<LogContainer>, mixed> $configured */
        $configured = (array) config('log-containers.containers', []);

        $pretend = (bool) $this->option('pretend');
        $total = 0;

        foreach (array_keys($configured) as $class) {
            if (! is_subclass_of($class, LogContainer::class)) {
                throw new InvalidArgumentException(sprintf(
                    'Configured container [%s] under log-containers.containers is not a %s subclass. Fix or remove the entry.',
                    is_string($class) ? $class : gettype($class),
                    LogContainer::class,
                ));
            }

            /** @var LogContainer $container */
            $container = $app->make($class);
            $days = $container->retentionDays();

            if ($days === null || $days <= 0) {
                continue;
            }

            $cutoff = Carbon::now()->subDays($days);
            $query = LogEntry::query()
                ->where('container_key', $class::key())
                ->where('created_at', '<', $cutoff);

            $count = (clone $query)->count();

            if ($count === 0) {
                continue;
            }

            if (! $pretend) {
                $ids = (clone $query)->pluck('id');
                LogTag::query()->whereIn('log_entry_id', $ids)->delete();
                $query->delete();
            }

            $this->line(sprintf('%s [%s]: %d %s older than %d days', $class::name(), $class::key(), $count, $pretend ? 'to delete' : 'deleted', $days));
            $total += $count;
        }

        $this->info(sprintf('%s %d entries across %d containers.', $pretend ? 'Would prune' : 'Pruned', $total, count($configured)));

        return self::SUCCESS;
    }
}
