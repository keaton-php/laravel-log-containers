<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Keaton\LogContainers\Facades\LogContainers;
use Keaton\LogContainers\Tests\Fixtures\WidgetLog;
use Keaton\LogContainers\Tests\TestCase;
use PHPUnit\Framework\ExpectationFailedException;

class TestFakeTest extends TestCase
{
    public function test_assertions_pass_for_what_was_logged(): void
    {
        LogContainers::fake();

        log_container(WidgetLog::class)->info('one');
        log_container(WidgetLog::class)->error('two');

        LogContainers::assertLogged(WidgetLog::class);
        LogContainers::assertLogged(WidgetLog::class, 'error');
        LogContainers::assertNotLogged(WidgetLog::class, 'critical');
        LogContainers::assertCount(WidgetLog::class, 2);
        LogContainers::assertCount(WidgetLog::class, 1, 'error');

        // by key, too
        LogContainers::assertLogged('widgets', 'info');
    }

    public function test_fake_only_counts_entries_written_after_it(): void
    {
        log_container(WidgetLog::class)->info('before the fake');

        LogContainers::fake();

        log_container(WidgetLog::class)->info('after the fake');

        LogContainers::assertCount(WidgetLog::class, 1);
    }

    public function test_assert_logged_fails_when_nothing_matched(): void
    {
        LogContainers::fake();

        $this->expectException(ExpectationFailedException::class);

        LogContainers::assertLogged(WidgetLog::class, 'error');
    }

    public function test_assert_count_fails_on_the_wrong_number(): void
    {
        LogContainers::fake();
        log_container(WidgetLog::class)->info('one');

        $this->expectException(ExpectationFailedException::class);

        LogContainers::assertCount(WidgetLog::class, 2);
    }

    public function test_assert_not_logged_fails_when_it_was_logged(): void
    {
        LogContainers::fake();
        log_container(WidgetLog::class)->info('one');

        $this->expectException(ExpectationFailedException::class);

        LogContainers::assertNotLogged(WidgetLog::class);
    }
}
