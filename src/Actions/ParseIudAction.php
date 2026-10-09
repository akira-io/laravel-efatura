<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\IudData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Support\Luhn;
use Illuminate\Validation\ValidationException;

final readonly class ParseIudAction
{
    public function handle(string $iud, string $field = 'iud'): IudData
    {
        if (! FiscalRules::isIud($iud) || ! Luhn::passes(substr($iud, 2))) {
            throw self::invalid($field);
        }

        try {
            return IudData::from([
                'repositoryCode'   => (int) substr($iud, 2, 1),
                'issueDate'        => '20' . substr($iud, 3, 2) . '-' . substr($iud, 5, 2) . '-' . substr($iud, 7, 2),
                'emitterTaxId'     => substr($iud, 9, 9),
                'ledCode'          => (int) substr($iud, 18, 5),
                'documentTypeCode' => self::documentType((int) substr($iud, 23, 2)),
                'documentNumber'   => (int) substr($iud, 25, 9),
                'randomCode'       => substr($iud, 34, 10),
            ]);
        } catch (ValidationException) {
            throw self::invalid($field);
        }
    }

    private static function documentType(int $code): ?DocumentType
    {
        return collect(DocumentType::cases())->first(static fn (DocumentType $type): bool => $type->code() === $code);
    }

    private static function invalid(string $field): ValidationException
    {
        return ValidationException::withMessages([$field => __('efatura::efatura.validation.iud_invalid', ['attribute' => $field])]);
    }
}
