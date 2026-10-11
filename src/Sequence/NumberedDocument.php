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

    /**
     * @return array<string, int|string|null>
     */
    public function context(): array
    {
        $scope = SequenceScope::forDocument($this->document);

        return [
            'documentNumber'   => $this->document->header->documentNumber,
            'iud'              => $this->iud,
            'emitterTaxId'     => $scope->emitterTaxId,
            'fiscalYear'       => $scope->year,
            'ledCode'          => $scope->ledCode,
            'documentTypeCode' => $scope->documentType->code(),
        ];
    }
}
