<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\IudData;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Sequence\NumberedDocument;
use Akira\Efatura\Sequence\SequenceScope;

final readonly class NumberDocumentAction
{
    public function __construct(private SequenceStore $sequences, private BuildIudAction $buildIud) {}

    public function handle(DocumentData $document, Environment $repository): NumberedDocument
    {
        $validated = $document::from($document);
        $allocated = $validated->header->documentNumber === null;
        $numbered  = $allocated ? $validated->withDocumentNumber($this->sequences->next(SequenceScope::forDocument($validated))) : $validated;

        $iud = $this->buildIud->handle(IudData::from([
            'repositoryCode'   => $repository,
            'issueDate'        => $numbered->header->issueDate,
            'emitterTaxId'     => $numbered->emitter->taxId?->value,
            'ledCode'          => $numbered->header->ledCode,
            'documentTypeCode' => $numbered->type(),
            'documentNumber'   => $numbered->header->documentNumber,
        ]));

        return new NumberedDocument($numbered, $iud, $allocated);
    }
}
