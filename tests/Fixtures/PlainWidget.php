<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with no tag-label contract and no __toString, to exercise the
 * "ClassName #id" fallback.
 */
class PlainWidget extends Model
{
    protected $table = 'widgets';

    protected $guarded = [];

    public $timestamps = false;
}
