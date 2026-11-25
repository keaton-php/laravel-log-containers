<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Unit;

use InvalidArgumentException;
use Keaton\LogContainers\Support\Redactor;
use PHPUnit\Framework\TestCase;

class RedactorTest extends TestCase
{
    public function test_a_redactor_with_no_rules_returns_the_context_unchanged(): void
    {
        $redactor = new Redactor([], []);
        $context = ['a' => 1, 'password' => 'secret'];

        $this->assertFalse($redactor->hasRules());
        $this->assertSame($context, $redactor->redact($context));
    }

    public function test_keys_are_replaced_wholesale_at_any_depth(): void
    {
        $redactor = new Redactor(['password'], []);

        $clean = $redactor->redact([
            'user' => 'operator-1',
            'password' => 'hunter2',
            'nested' => ['password' => 'again'],
        ]);

        $this->assertSame('operator-1', $clean['user']);
        $this->assertSame('[redacted]', $clean['password']);
        $this->assertSame('[redacted]', $clean['nested']['password']);
    }

    public function test_patterns_redact_inside_string_values(): void
    {
        $redactor = new Redactor([], ['/\d{3}-\d{2}-\d{4}/']);

        $clean = $redactor->redact(['note' => 'ssn is 123-45-6789 on file']);

        $this->assertSame('ssn is [redacted] on file', $clean['note']);
    }

    public function test_non_string_values_are_left_alone_by_patterns(): void
    {
        $redactor = new Redactor([], ['/\d+/']);

        $clean = $redactor->redact(['count' => 42]);

        $this->assertSame(42, $clean['count']);
    }

    public function test_an_invalid_pattern_throws_rather_than_silently_keeping_the_value(): void
    {
        // A broken pattern must never leave the original (possibly secret)
        // value in place: it has to fail loudly.
        $redactor = new Redactor([], ['/(unterminated/']);

        // preg_replace() also raises an E_WARNING for a pattern that does not
        // compile. It is expected here, so the test collects it.
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, E_WARNING);

        try {
            $redactor->redact(['note' => 'anything']);
            $this->fail('an invalid pattern should have thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('/(unterminated/', $e->getMessage());
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $warnings);
        $this->assertStringStartsWith('preg_replace(): ', $warnings[0]);
    }
}
