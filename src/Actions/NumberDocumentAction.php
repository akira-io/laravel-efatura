<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\IudData;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Sequence\NumberedDocument;
use Akira\Efatura\Sequence\SequenceScope;
use Illuminate\Validation\ValidationException;

final readonly class NumberDocumentAction
{
    public function __construct(private SequenceStore $sequences, private BuildIudAction $buildIud, private ParseIudAction $parseIud) {}

    public function handle(DocumentData $document, Environment $repository, ?string $iud = null): NumberedDocument
    {
        $validated  = $document::from($document);
        $allocated  = $validated->header->documentNumber === null;
        $randomCode = $iud === null ? null : $this->parseIud->handle($iud)->randomCode;
        if ($allocated && $iud !== null) {
            throw self::mismatch();
        }

        $numbered = $allocated ? $validated->withDocumentNumber($this->sequences->next(SequenceScope::forDocument($validated))) : $validated;
        $built    = $this->buildIud->handle(IudData::from([
            'repositoryCode'   => $repository,
            'issueDate'        => $numbered->header->issueDate,
            'emitterTaxId'     => $numbered->emitter->taxId?->value,
            'ledCode'          => $numbered->header->ledCode,
            'documentTypeCode' => $numbered->type(),
            'documentNumber'   => $numbered->header->documentNumber,
            'randomCode'       => $randomCode,
        ]));

        if ($iud !== null && $built !== $iud) {
            throw self::mismatch();
        }

        return new NumberedDocument($numbered, $built, $allocated);
    }

    private static function mismatch(): ValidationException
    {
        return ValidationException::withMessages(['iud' => __('efatura::efatura.validation.iud_mismatch', ['attribute' => 'iud'])]);
    }
}
