<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Keaton\LogContainers\LogContainer;

class WidgetLog extends LogContainer
{
    public static function key(): string
    {
        return 'widgets';
    }

    public static function name(): string
    {
        return 'Widgets';
    }

    public function defaultContext(): array
    {
        return ['source' => 'widget-log'];
    }
}
