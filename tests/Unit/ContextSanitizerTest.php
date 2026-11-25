<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Unit;

use Keaton\LogContainers\Support\ContextSanitizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

enum SanitizerColor: string
{
    case Red = 'red';
}

class ThrowingToArray
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        throw new RuntimeException('cannot convert');
    }
}

class ContextSanitizerTest extends TestCase
{
    public function test_scalars_pass_through_untouched(): void
    {
        $context = ['a' => 1, 'b' => 'two', 'c' => true, 'd' => null, 'e' => 1.5];

        $this->assertSame($context, (new ContextSanitizer)->sanitize($context));
    }

    public function test_closures_and_resources_become_labels(): void
    {
        $resource = fopen('php://memory', 'r');

        $clean = (new ContextSanitizer)->sanitize([
            'closure' => fn () => 1,
            'resource' => $resource,
        ]);

        fclose($resource);

        $this->assertSame('Closure', $clean['closure']);
        $this->assertSame('resource(stream)', $clean['resource']);
    }

    public function test_non_finite_floats_are_stringified(): void
    {
        $clean = (new ContextSanitizer)->sanitize(['nan' => NAN, 'inf' => INF]);

        $this->assertSame('NAN', $clean['nan']);
        $this->assertSame('INF', $clean['inf']);
    }

    public function test_backed_enums_use_their_value(): void
    {
        $clean = (new ContextSanitizer)->sanitize(['color' => SanitizerColor::Red]);

        $this->assertSame('red', $clean['color']);
    }

    public function test_recursion_is_broken_and_the_rest_survives(): void
    {
        $node = new stdClass;
        $node->self = $node;
        $node->name = 'leaf';

        $clean = (new ContextSanitizer)->sanitize(['node' => $node]);

        $this->assertSame('leaf', $clean['node']['name']);
        $this->assertStringContainsString('recursion', (string) $clean['node']['self']);
    }

    public function test_the_original_array_is_never_mutated(): void
    {
        $context = ['closure' => $closure = fn () => 1];

        (new ContextSanitizer)->sanitize($context);

        $this->assertSame($closure, $context['closure']);
    }

    public function test_a_throwing_conversion_becomes_a_diagnostic_rather_than_aborting(): void
    {
        $clean = (new ContextSanitizer)->sanitize(['bad' => new ThrowingToArray]);

        $this->assertSame(ThrowingToArray::class, $clean['bad']['__unconvertible']);
        $this->assertSame('toArray', $clean['bad']['via']);
        $this->assertStringContainsString('cannot convert', $clean['bad']['error']);
    }
}
