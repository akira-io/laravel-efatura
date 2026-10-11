<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Enums\ArtifactKind;
use Akira\Efatura\Storage\StoredArtifact;

interface ArtifactStore
{
    public function put(string $iud, ArtifactKind $kind, string $contents): StoredArtifact;

    public function get(StoredArtifact $artifact): string;
}
