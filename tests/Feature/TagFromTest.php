<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Feature;

use Keaton\LogContainers\Support\Tag;
use Keaton\LogContainers\Tests\Fixtures\PlainWidget;
use Keaton\LogContainers\Tests\Fixtures\Widget;
use Keaton\LogContainers\Tests\TestCase;

class TagFromTest extends TestCase
{
    public function test_a_string_is_a_label_only_tag(): void
    {
        $this->assertSame(['type' => null, 'id' => null, 'label' => 'checkout'], Tag::from('checkout'));
    }

    public function test_a_model_is_a_link_with_the_label_it_provides(): void
    {
        $widget = Widget::create(['name' => 'gizmo']);

        $this->assertSame(
            ['type' => Widget::class, 'id' => (string) $widget->id, 'label' => 'Widget: gizmo'],
            Tag::from($widget),
        );
    }

    public function test_a_model_without_a_label_falls_back_to_its_class_and_key(): void
    {
        $widget = PlainWidget::create(['name' => 'gizmo']);

        $this->assertSame(
            ['type' => PlainWidget::class, 'id' => (string) $widget->id, 'label' => 'PlainWidget #'.$widget->id],
            Tag::from($widget),
        );
    }
}
