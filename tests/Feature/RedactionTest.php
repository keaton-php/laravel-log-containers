<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogSession;
use Keaton\LogContainers\Tests\Fixtures\SecretLog;
use Keaton\LogContainers\Tests\Fixtures\SecretToArray;
use Keaton\LogContainers\Tests\Fixtures\SecretToString;
use Keaton\LogContainers\Tests\TestCase;

class RedactionTest extends TestCase
{
    public function test_a_container_redacts_keys_before_storing(): void
    {
        log_container(SecretLog::class)->info('login', [
            'user' => 'operator-1',
            'password' => 'hunter2',
        ]);

        $context = LogEntry::query()->firstOrFail()->context;
        $this->assertSame('operator-1', $context['user']);
        $this->assertSame('[redacted]', $context['password']);
    }

    public function test_a_container_redacts_patterns_inside_string_values(): void
    {
        log_container(SecretLog::class)->info('note', [
            'memo' => 'ssn 123-45-6789 recorded',
        ]);

        $this->assertSame('ssn [redacted] recorded', LogEntry::query()->firstOrFail()->context['memo']);
    }

    public function test_a_session_redacts_its_context_before_storing(): void
    {
        log_container(SecretLog::class)
            ->context(['password' => 'hunter2', 'account' => 'public'])
            ->startSession('login');

        $context = LogSession::query()->firstOrFail()->context;
        $this->assertSame('[redacted]', $context['password']);
        $this->assertSame('public', $context['account']);
    }

    public function test_an_entry_redacts_secrets_exposed_by_object_conversion(): void
    {
        log_container(SecretLog::class)->info('payload', [
            'blob' => new SecretToArray,
            'note' => new SecretToString,
        ]);

        $context = LogEntry::query()->firstOrFail()->context;

        // A key the object only revealed through toArray() is still redacted.
        $this->assertSame('[redacted]', $context['blob']['password']);
        $this->assertSame('public', $context['blob']['account']);

        // A pattern the object only revealed through __toString() is still scrubbed.
        $this->assertSame('ssn [redacted] on file', $context['note']);
    }

    public function test_a_session_redacts_secrets_exposed_by_object_conversion(): void
    {
        log_container(SecretLog::class)
            ->context([
                'blob' => new SecretToArray,
                'note' => new SecretToString,
            ])
            ->startSession('login');

        $context = LogSession::query()->firstOrFail()->context;

        $this->assertSame('[redacted]', $context['blob']['password']);
        $this->assertSame('public', $context['blob']['account']);
        $this->assertSame('ssn [redacted] on file', $context['note']);
    }
}
