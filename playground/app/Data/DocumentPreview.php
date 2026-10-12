<?php

declare(strict_types=1);

namespace App\Data;

use Akira\Efatura\Data\DocumentData;

final readonly class DocumentPreview
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(
        public DocumentData $document,
        public string $iud,
        public string $xml,
        public array $violations,
    ) {}

    public function schemaValid(): bool
    {
        return $this->violations === [];
    }
}
