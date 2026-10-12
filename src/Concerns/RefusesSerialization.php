<?php

declare(strict_types=1);

namespace Akira\Efatura\Concerns;

use Akira\Efatura\Exceptions\DefinitionException;

trait RefusesSerialization
{
    /**
     * @return array<never>
     */
    public function __serialize(): array
    {
        throw DefinitionException::configurationSerialization();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw DefinitionException::configurationSerialization();
    }
}
