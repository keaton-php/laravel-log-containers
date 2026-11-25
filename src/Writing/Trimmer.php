<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Writing;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Lottery;
use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogTag;

/**
 * Keeps a container under its row cap. Counting the table on every write would
 * be slow, so a few writes in a hundred (trim.odds) do the check.
 *
 * @internal
 */
final class Trimmer
{
    public function __construct(
        private Repository $config,
        private ContainerSettings $settings,
    ) {}

    public function maybeTrim(LogContainer $container): void
    {
        $max = $this->settings->maxEntries($container);

        if ($max === null || $max <= 0) {
            return;
        }

        $odds = (int) $this->config->get('log-containers.trim.odds', 2);

        Lottery::odds(max(1, $odds), 100)
            ->winner(fn () => $this->trim($container::key(), $max))
            ->choose();
    }

    /**
     * Delete the oldest entries of a container, and their tags, down to $max.
     */
    public function trim(string $key, int $max): void
    {
        $count = LogEntry::query()->where('container_key', $key)->count();

        if ($count <= $max) {
            return;
        }

        $ids = LogEntry::query()
            ->where('container_key', $key)
            ->orderBy('id')
            ->limit($count - $max)
            ->pluck('id');

        LogEntry::query()->whereIn('id', $ids)->delete();
        LogTag::query()->whereIn('log_entry_id', $ids)->delete();
    }
}
