<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

use Akira\Efatura\Exceptions\DfaException;

final readonly class LibraryRequirement
{
    /**
     * @param class-string $entryClass
     */
    public function __construct(
        public string $package,
        public string $entryClass,
    ) {}

    public function installed(): bool
    {
        return class_exists($this->entryClass);
    }

    public function ensureInstalled(): void
    {
        if (! $this->installed()) {
            throw DfaException::rendererUnavailable($this->package);
        }
    }
}
