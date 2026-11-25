<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When false, every container stops writing entries. Level methods still
    | return a LogEntry object, but it is never persisted, so application code
    | does not need to change.
    |
    */

    'enabled' => env('LOG_CONTAINERS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Per-container overrides
    |--------------------------------------------------------------------------
    |
    | Keyed by container class. Each container already declares its own key,
    | retention and allowed levels in code; this is where an application can
    | override them without touching the class. 'levels' is an explicit list,
    | 'level' names one of the presets below.
    |
    |   App\Logging\OrderLog::class => [
    |       'enabled'  => true,
    |       'levels'   => ['error', 'critical'],   // or:
    |       'level'    => 'problems',
    |       'max_entries' => 50000,
    |   ],
    |
    */

    'containers' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Level presets
    |--------------------------------------------------------------------------
    |
    | Named sets of levels a container may opt into with 'level' => 'problems'.
    |
    */

    'level_presets' => [
        'everything' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'exception', 'success'],
        'standard' => ['info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'exception'],
        'problems' => ['warning', 'error', 'critical', 'alert', 'emergency', 'exception'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Watches
    |--------------------------------------------------------------------------
    |
    | Code-defined watches that fire when a matching entry lands. Keyed by the
    | watch class. 'containers' is a list of container keys, or ['*'] for every
    | container.
    |
    |   App\Logging\Watches\RepeatedFailureWatch::class => [
    |       'containers' => ['orders'],
    |       'enabled'    => true,
    |   ],
    |
    */

    'watches' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Trimming
    |--------------------------------------------------------------------------
    |
    | Each container may declare a maximum number of entries. Rather than count
    | the table on every write, the trim runs probabilistically: 'odds' in 100
    | writes trigger a check that deletes the oldest rows back down to the max.
    |
    */

    'trim' => [
        'odds' => env('LOG_CONTAINERS_TRIM_ODDS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mirror to the Laravel log
    |--------------------------------------------------------------------------
    |
    | The default for whether entries are also written to the standard Laravel
    | log. A container may override this with mirrorsToLaravelLog() and
    | laravelLogChannel(). A null channel is the default stack.
    |
    */

    'mirror_to_laravel_log' => env('LOG_CONTAINERS_MIRROR', false),

    'laravel_log_channel' => env('LOG_CONTAINERS_CHANNEL'),

];
