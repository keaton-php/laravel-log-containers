<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A sticky tag linking an entry to an Eloquent record (or a plain label).
 *
 * @property int $id
 * @property int $log_entry_id
 * @property string|null $taggable_type
 * @property string|null $taggable_id
 * @property string $label
 */
class LogTag extends Model
{
    protected $table = 'log_tags';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<LogEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(LogEntry::class, 'log_entry_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function taggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isModelTag(): bool
    {
        return $this->taggable_type !== null;
    }
}
