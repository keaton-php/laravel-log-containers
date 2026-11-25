<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Keaton\LogContainers\Tests\Fixtures\RetiringLog;
use Keaton\LogContainers\Tests\TestCase;

class PruneCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('log-containers.containers', [RetiringLog::class => []]);
    }

    public function test_it_deletes_entries_older_than_the_retention_window(): void
    {
        $old = log_container(RetiringLog::class)->info('old');
        $old->forceFill(['created_at' => Carbon::now()->subDays(10)])->save();

        $recent = log_container(RetiringLog::class)->info('recent');

        $this->artisan('log-containers:prune')->assertSuccessful();

        $this->assertDatabaseMissing('log_entries', ['id' => $old->id]);
        $this->assertDatabaseHas('log_entries', ['id' => $recent->id]);
    }

    public function test_pretend_deletes_nothing(): void
    {
        $old = log_container(RetiringLog::class)->info('old');
        $old->forceFill(['created_at' => Carbon::now()->subDays(10)])->save();

        $this->artisan('log-containers:prune', ['--pretend' => true])->assertSuccessful();

        $this->assertDatabaseHas('log_entries', ['id' => $old->id]);
    }

    public function test_it_removes_the_tags_of_pruned_entries(): void
    {
        $old = log_container(RetiringLog::class)->tagWith('batch')->info('old');
        $old->forceFill(['created_at' => Carbon::now()->subDays(10)])->save();

        $this->assertDatabaseCount('log_tags', 1);

        $this->artisan('log-containers:prune')->assertSuccessful();

        $this->assertDatabaseCount('log_tags', 0);
    }

    public function test_an_invalid_configured_container_class_throws(): void
    {
        config()->set('log-containers.containers', ['Not\\A\\Real\\Container' => []]);

        $this->expectException(InvalidArgumentException::class);

        $this->artisan('log-containers:prune')->run();
    }
}
