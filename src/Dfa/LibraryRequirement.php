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

    public function ensureInstalled(): void
    {
        if (! class_exists($this->entryClass)) {
            throw DfaException::rendererUnavailable($this->package);
        }
    }
}
