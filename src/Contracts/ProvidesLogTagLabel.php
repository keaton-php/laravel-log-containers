<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Contracts;

/**
 * A model that controls how it reads as a log tag. Without it, a tagged model
 * falls back to its __toString(), then to "ClassName #id".
 */
interface ProvidesLogTagLabel
{
    public function logTagLabel(): string;
}
