<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Tests\Fixtures\CustomLog;
use Keaton\LogContainers\Tests\Fixtures\Recorder;
use Keaton\LogContainers\Tests\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * What a subclass of LogContainer can see and rely on. That surface is frozen
 * once 1.0 is tagged; DECISIONS.md explains each choice.
 */
class SubclassSurfaceTest extends TestCase
{
    public function test_the_methods_a_subclass_can_see_are_the_listed_ones(): void
    {
        $visible = [];

        foreach ((new ReflectionClass(LogContainer::class))->getMethods() as $method) {
            if (! $method->isPrivate()) {
                $visible[$method->getName()] = ($method->isPublic() ? 'public' : 'protected').($method->isFinal() ? ' final' : '');
            }
        }

        ksort($visible);

        // Adding a public or protected method to LogContainer freezes it at the
        // next release. If that is intended, add it here.
        $this->assertSame([
            '__call' => 'public final',
            '__callStatic' => 'public final',
            'allowedLevels' => 'public',
            'defaultContext' => 'public',
            'key' => 'public',
            'laravelLogChannel' => 'public',
            'maxEntries' => 'public',
            'mirrorsToLaravelLog' => 'public',
            'name' => 'public',
            'redactKeys' => 'public',
            'redactPatterns' => 'public',
            'retentionDays' => 'public',
            'startSession' => 'public final',
            'tags' => 'public final',
            'write' => 'protected',
        ], $visible);
    }

    public function test_the_base_class_has_no_constructor_and_no_visible_properties(): void
    {
        $class = new ReflectionClass(LogContainer::class);

        $this->assertNull($class->getConstructor());

        foreach ($class->getProperties() as $property) {
            $this->assertTrue($property->isPrivate(), "LogContainer::\${$property->getName()} is visible to subclasses");
        }
    }

    public function test_the_classes_behind_the_container_are_final_and_internal(): void
    {
        $files = array_merge(
            glob(__DIR__.'/../../src/Writing/*.php') ?: [],
            [__DIR__.'/../../src/Support/LevelArguments.php', __DIR__.'/../../src/Support/Tag.php'],
        );

        $this->assertCount(10, $files);

        foreach ($files as $file) {
            $namespace = str_contains($file, '/Writing/') ? 'Writing' : 'Support';
            $class = new ReflectionClass('Keaton\\LogContainers\\'.$namespace.'\\'.basename($file, '.php'));

            $this->assertTrue($class->isFinal(), "{$class->getName()} is not final");
            $this->assertStringContainsString('@internal', (string) $class->getDocComment(), "{$class->getName()} is not marked @internal");
        }
    }

    public function test_a_subclass_can_have_a_constructor_of_its_own(): void
    {
        $this->app->singleton(Recorder::class);

        $entry = log_container(CustomLog::class)->info('made');

        $this->assertSame([$entry->id], app(Recorder::class)->ids);
    }

    public function test_a_subclass_can_wrap_write(): void
    {
        $this->app->singleton(Recorder::class);

        $entry = CustomLog::error('payment failed', ['order_id' => 42]);

        $this->assertSame('PAYMENT FAILED', $entry->message);
        $this->assertSame('error', $entry->level);
        $this->assertSame(['order_id' => 42], $entry->context);
    }

    public function test_a_subclass_can_call_write_from_a_method_of_its_own(): void
    {
        $this->app->singleton(Recorder::class);

        $entry = log_container(CustomLog::class)->during('review')->audit('checked');

        $this->assertTrue($entry->exists);
        $this->assertSame('notice', $entry->level);
        $this->assertSame('review', $entry->during);
        $this->assertSame(['audit' => true], $entry->context);
    }

    public function test_a_subclass_method_named_like_a_private_one_does_not_replace_it(): void
    {
        $this->app->singleton(Recorder::class);

        $log = log_container(CustomLog::class);

        $this->assertSame('not the base method', $log->applyTag());

        $entry = $log->tagWith('checkout')->info('tagged');

        $this->assertSame(['checkout'], $entry->tags()->pluck('label')->all());
        $this->assertSame(1, LogEntry::query()->count());
    }

    public function test_the_methods_behind_the_magic_names_are_private(): void
    {
        foreach (['applyType', 'applyContext', 'applyTag', 'applyClearTags', 'startDoing', 'startDuring', 'writeException'] as $method) {
            $this->assertTrue((new ReflectionMethod(LogContainer::class, $method))->isPrivate(), "LogContainer::{$method}() is not private");
        }
    }
}
