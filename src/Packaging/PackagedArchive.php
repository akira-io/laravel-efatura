<?php

declare(strict_types=1);

namespace Akira\Efatura\Packaging;

use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\PackageKind;
use Illuminate\Support\Str;

final readonly class PackagedArchive
{
    /**
     * @param list<string> $entries
     * @param list<string> $emitters
     */
    public function __construct(
        public string $bytes,
        public PackageKind $kind,
        public array $entries,
        public Environment $repository,
        public array $emitters,
    ) {}

    /**
     * @return list<string>
     */
    public function identifiers(): array
    {
        return array_map(static fn (string $entry): string => Str::beforeLast($entry, '.xml'), $this->entries);
    }
}
