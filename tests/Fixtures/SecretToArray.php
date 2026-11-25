<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

/**
 * An object that only reveals its secret once the sanitizer turns it into an
 * array. Before sanitization there is no "password" key for the redactor to
 * match, so this proves redaction runs after object-to-array conversion.
 */
class SecretToArray
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'password' => 'hunter2',
            'account' => 'public',
        ];
    }
}
