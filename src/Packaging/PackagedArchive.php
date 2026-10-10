<?php

declare(strict_types=1);

namespace Akira\Efatura\Packaging;

use Akira\Efatura\Enums\PackageKind;

final readonly class PackagedArchive
{
    /**
     * @param list<string> $entries
     */
    public function __construct(
        public string $bytes,
        public PackageKind $kind,
        public array $entries,
    ) {}
}
