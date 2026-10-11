<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

final readonly class DocumentLookup
{
    public function __construct(
        public string $iud,
        public bool $found,
        public ?string $xml,
    ) {}
}
