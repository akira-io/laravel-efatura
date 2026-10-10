<?php

declare(strict_types=1);

namespace Akira\Efatura\Packaging;

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Signing\SignedXml;

final readonly class PreparedDocument
{
    public function __construct(
        public DocumentData $document,
        public string $iud,
        public string $unsignedXml,
        public SignedXml $signed,
        public PackagedArchive $archive,
        public bool $allocated,
    ) {}
}
