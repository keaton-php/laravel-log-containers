<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One timed scope opened by doing(): a run with a duration and a pass/fail
 * outcome, and the entries written inside it. A doing() block inside another
 * one is a child of it.
 *
 * @property int $id
 * @property string $container_key
 * @property int|null $parent_id
 * @property string|null $name
 * @property string $status
 * @property array<string, mixed>|null $context
 * @property int $entry_count
 * @property int $error_count
 * @property int|null $duration_ms
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 */
class LogSession extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'log_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'parent_id' => 'integer',
        'context' => 'array',
        'entry_count' => 'integer',
        'error_count' => 'integer',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * @return HasMany<LogEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LogEntry::class, 'session_id');
    }

    /**
     * The session of the block this one ran inside.
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The sessions of the blocks that ran inside this one.
     *
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
