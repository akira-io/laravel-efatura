<?php

declare(strict_types=1);

namespace Akira\Efatura\Sequence;

use Akira\Efatura\Data\DocumentData;

final readonly class NumberedDocument
{
    public function __construct(
        public DocumentData $document,
        public string $iud,
        public bool $allocated,
    ) {}
}
