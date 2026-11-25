<?php

declare(strict_types=1);

namespace Keaton\LogContainers;

use Illuminate\Support\Str;

/**
 * One id for a whole execution (an HTTP request, a queue job or a console
 * command), so every entry written during that run can be pulled back together.
 *
 * Bound as a singleton, so the id is the same everywhere in the run. The id is
 * minted lazily the first time it is read, or eagerly by one of the
 * initializeFor* helpers. continueFrom() carries an id across a boundary the
 * container cannot see on its own, such as an event handed to a queued
 * listener.
 */
class ExecutionContext
{
    private ?string $id = null;

    private ?string $type = null;

    /**
     * @var array<string, mixed>
     */
    private array $metadata = [];

    public function id(): string
    {
        return $this->id ??= $this->generate();
    }

    public function hasId(): bool
    {
        return $this->id !== null;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function addMetadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    public function initializeForHttp(string $method, string $path, ?string $ip = null): static
    {
        $this->setType('http')->addMetadata(['method' => $method, 'path' => $path, 'ip' => $ip]);
        $this->id();

        return $this;
    }

    public function initializeForConsole(string $command): static
    {
        $this->setType('console')->addMetadata(['command' => $command, 'pid' => getmypid()]);
        $this->id();

        return $this;
    }

    public function initializeForJob(string $job, ?string $queue = null): static
    {
        $this->setType('queue')->addMetadata(['job' => $job, 'queue' => $queue, 'pid' => getmypid()]);
        $this->id();

        return $this;
    }

    /**
     * Continue an execution started elsewhere. Pass the id you carried across
     * the boundary; the type and metadata are merged when given.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function continueFrom(string $id, ?string $type = null, ?array $metadata = null): static
    {
        $this->id = $id;

        if ($type !== null) {
            $this->type = $type;
        }

        if ($metadata !== null) {
            $this->metadata = array_merge($this->metadata, $metadata);
        }

        return $this;
    }

    /**
     * Forget the current id, type and metadata. Useful between iterations of a
     * long-running worker that should not share one id across many jobs.
     */
    public function reset(): static
    {
        $this->id = null;
        $this->type = null;
        $this->metadata = [];

        return $this;
    }

    private function generate(): string
    {
        return (string) Str::uuid();
    }
}
