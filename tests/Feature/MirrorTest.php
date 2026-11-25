<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Keaton\LogContainers\Facades\LogContainers;
use Keaton\LogContainers\Tests\Fixtures\MirrorLog;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use RuntimeException;

class MirrorTest extends TestCase
{
    /**
     * @var array<int, array{level: string, message: string}>
     */
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->captured = [];
        Log::listen(function (MessageLogged $message): void {
            $this->captured[] = ['level' => $message->level, 'message' => $message->message];
        });
    }

    public function test_a_mirroring_container_also_writes_to_the_laravel_log(): void
    {
        log_container(MirrorLog::class)->error('mirror me', ['a' => 1]);

        $this->assertCount(1, $this->captured);
        $this->assertSame('error', $this->captured[0]['level']);
        $this->assertSame('mirror me', $this->captured[0]['message']);
    }

    public function test_the_exception_level_mirrors_as_error(): void
    {
        log_container(MirrorLog::class)->exception('it broke', new RuntimeException('boom'));

        $this->assertSame('error', $this->captured[0]['level']);
    }

    public function test_a_non_mirroring_container_does_not_touch_the_laravel_log(): void
    {
        log_container(WidgetLog::class)->error('private');

        $this->assertCount(0, $this->captured);
    }

    public function test_faking_suppresses_mirroring(): void
    {
        LogContainers::fake();

        log_container(MirrorLog::class)->error('should not mirror');

        $this->assertCount(0, $this->captured);
    }
}
