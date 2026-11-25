<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Keaton\LogContainers\Contracts\ProvidesLogTagLabel;

class Widget extends Model implements ProvidesLogTagLabel
{
    protected $table = 'widgets';

    protected $guarded = [];

    public $timestamps = false;

    public function logTagLabel(): string
    {
        return "Widget: {$this->name}";
    }
}
