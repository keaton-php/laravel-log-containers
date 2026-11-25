<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Unit;

use InvalidArgumentException;
use Keaton\LogContainers\Support\LevelArguments;
use PHPUnit\Framework\TestCase;

class LevelArgumentsTest extends TestCase
{
    public function test_a_message_alone_gets_an_empty_context(): void
    {
        $this->assertSame(['Order placed', []], LevelArguments::parse('info', ['Order placed']));
    }

    public function test_positional_arguments_are_read_in_order(): void
    {
        $this->assertSame(['Order placed', ['id' => 42]], LevelArguments::parse('info', ['Order placed', ['id' => 42]]));
    }

    public function test_named_arguments_are_read_by_name(): void
    {
        $this->assertSame(
            ['Order placed', ['id' => 42]],
            LevelArguments::parse('info', ['context' => ['id' => 42], 'message' => 'Order placed']),
        );
    }

    public function test_a_positional_message_can_be_combined_with_a_named_context(): void
    {
        $this->assertSame(['Order placed', ['id' => 42]], LevelArguments::parse('info', ['Order placed', 'context' => ['id' => 42]]));
    }

    public function test_a_missing_message_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Log level [info] expects a string message as its first argument, got null.');

        LevelArguments::parse('info', []);
    }

    public function test_a_non_string_message_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expects a string message as its first argument, got int.');

        LevelArguments::parse('info', [42]);
    }

    public function test_a_non_array_context_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Log level [error] expects an array context as its second argument, got string.');

        LevelArguments::parse('error', ['Payment failed', 'order 42']);
    }

    public function test_a_third_argument_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Log level [info] received unexpected argument(s) [2]');

        LevelArguments::parse('info', ['Order placed', [], 'extra']);
    }

    public function test_an_unknown_named_argument_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('received unexpected argument(s) [level]');

        LevelArguments::parse('info', ['Order placed', 'level' => 'debug']);
    }

    public function test_an_argument_passed_twice_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Log level [info] received argument [message] both positionally and by name; pass it once.');

        LevelArguments::parse('info', ['first', 'message' => 'second']);
    }
}
