<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Unit;

use Keaton\LogContainers\ExecutionContext;
use PHPUnit\Framework\TestCase;

class ExecutionContextTest extends TestCase
{
    public function test_the_id_is_minted_lazily_and_is_stable(): void
    {
        $context = new ExecutionContext;

        $this->assertFalse($context->hasId());

        $id = $context->id();

        $this->assertTrue($context->hasId());
        $this->assertSame($id, $context->id());
    }

    public function test_continue_from_adopts_an_id_type_and_metadata(): void
    {
        $context = (new ExecutionContext)->addMetadata(['a' => 1]);

        $context->continueFrom('run-123', 'queue', ['b' => 2]);

        $this->assertSame('run-123', $context->id());
        $this->assertSame('queue', $context->type());
        $this->assertSame(['a' => 1, 'b' => 2], $context->metadata());
    }

    public function test_reset_clears_everything_for_the_next_iteration(): void
    {
        $context = (new ExecutionContext)->initializeForConsole('app:run');
        $first = $context->id();

        $context->reset();

        $this->assertFalse($context->hasId());
        $this->assertNull($context->type());
        $this->assertSame([], $context->metadata());
        $this->assertNotSame($first, $context->id());
    }

    public function test_initialize_helpers_set_the_type_and_metadata(): void
    {
        $http = (new ExecutionContext)->initializeForHttp('GET', '/orders', '127.0.0.1');
        $this->assertSame('http', $http->type());
        $this->assertSame('GET', $http->metadata()['method']);

        $job = (new ExecutionContext)->initializeForJob('App\\Jobs\\Sync', 'default');
        $this->assertSame('queue', $job->type());
        $this->assertSame('App\\Jobs\\Sync', $job->metadata()['job']);
    }
}
