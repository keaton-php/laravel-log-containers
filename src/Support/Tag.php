<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Support;

use Illuminate\Database\Eloquent\Model;
use Keaton\LogContainers\Contracts\ProvidesLogTagLabel;
use ReflectionMethod;

/**
 * Turns what tagWith() was given into the tag stored with each entry.
 *
 * @internal
 */
final class Tag
{
    /**
     * A string is a label-only tag. A model is a polymorphic link plus a label.
     *
     * @return array{type: string|null, id: string|null, label: string}
     */
    public static function from(Model|string $tag): array
    {
        if (is_string($tag)) {
            return ['type' => null, 'id' => null, 'label' => $tag];
        }

        return ['type' => $tag->getMorphClass(), 'id' => (string) $tag->getKey(), 'label' => self::label($tag)];
    }

    private static function label(Model $model): string
    {
        if ($model instanceof ProvidesLogTagLabel) {
            return $model->logTagLabel();
        }

        // Eloquent's own __toString() is the model as JSON; only one a model declares is a label.
        if ((new ReflectionMethod($model, '__toString'))->getDeclaringClass()->getName() !== Model::class) {
            return (string) $model;
        }

        return class_basename($model).' #'.$model->getKey();
    }
}
