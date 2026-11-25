<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

/**
 * An object whose secret only appears once the sanitizer casts it to a string.
 * Before that cast the value is not a string, so the pattern redactor skips it;
 * this proves pattern redaction runs after object-to-string conversion.
 */
class SecretToString
{
    public function __toString(): string
    {
        return 'ssn 123-45-6789 on file';
    }
}
