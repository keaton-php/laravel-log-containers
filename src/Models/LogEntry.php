<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One structured log entry: a queryable row on a container's channel.
 *
 * @property int $id
 * @property string $container_key
 * @property string $level
 * @property string $message
 * @property string|null $type
 * @property string|null $doing
 * @property string|null $during
 * @property int|null $session_id
 * @property string|null $execution_id
 * @property array<string, mixed>|null $context
 * @property string|null $exception_class
 * @property string|null $exception_message
 * @property string|null $exception_file
 * @property int|null $exception_line
 * @property string|null $exception_trace
 * @property string|null $user_id
 * @property string|null $environment
 * @property array<int, string>|null $triggered_watches
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class LogEntry extends Model
{
    protected $table = 'log_entries';

    protected $guarded = ['id'];

    protected $casts = [
        'context' => 'array',
        'triggered_watches' => 'array',
        'session_id' => 'integer',
        'exception_line' => 'integer',
    ];

    /**
     * @return BelongsTo<LogSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LogSession::class, 'session_id');
    }

    /**
     * @return HasMany<LogTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(LogTag::class, 'log_entry_id');
    }

    /**
     * @param  Builder<LogEntry>  $query
     * @return Builder<LogEntry>
     */
    public function scopeForContainer(Builder $query, string $containerKey): Builder
    {
        return $query->where('container_key', $containerKey);
    }

    /**
     * @param  Builder<LogEntry>  $query
     * @return Builder<LogEntry>
     */
    public function scopeForExecution(Builder $query, string $executionId): Builder
    {
        return $query->where('execution_id', $executionId);
    }

    /**
     * Every entry tagged with a given model: "show me every log that touched
     * this record".
     *
     * @param  Builder<LogEntry>  $query
     * @return Builder<LogEntry>
     */
    public function scopeTaggedWith(Builder $query, Model $model): Builder
    {
        return $query->whereHas('tags', function (Builder $tags) use ($model): void {
            $tags->where('taggable_type', $model->getMorphClass())
                ->where('taggable_id', (string) $model->getKey());
        });
    }

    public function hasException(): bool
    {
        return $this->exception_class !== null;
    }
}
