<?php

declare(strict_types=1);

namespace Akira\Efatura\Storage;

use Akira\Efatura\Enums\ArtifactKind;

final readonly class StoredArtifact
{
    public function __construct(
        public string $iud,
        public ArtifactKind $kind,
        public string $disk,
        public string $path,
        public int $size,
        public string $checksum,
    ) {}
}
