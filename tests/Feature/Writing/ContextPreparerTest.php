<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature\Writing;

use Keaton\LogContainers\Tests\Fixtures\SecretLog;
use Keaton\LogContainers\Tests\Fixtures\SecretToArray;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use Keaton\LogContainers\Writing\ContextPreparer;

class ContextPreparerTest extends TestCase
{
    public function test_the_call_wins_over_the_scope_and_the_scope_over_the_default(): void
    {
        $context = (new ContextPreparer)->prepare(
            log_container(WidgetLog::class),
            ['source' => 'scope', 'request' => 'scope'],
            ['request' => 'call'],
        );

        $this->assertSame(['source' => 'scope', 'request' => 'call'], $context);
    }

    public function test_the_default_context_is_there_when_nothing_else_is(): void
    {
        $this->assertSame(['source' => 'widget-log'], (new ContextPreparer)->prepare(log_container(WidgetLog::class), []));
    }

    public function test_redaction_runs_after_an_object_has_been_made_json_safe(): void
    {
        $context = (new ContextPreparer)->prepare(log_container(SecretLog::class), [], ['blob' => new SecretToArray]);

        $this->assertSame('[redacted]', $context['blob']['password']);
        $this->assertSame('public', $context['blob']['account']);
    }
}
