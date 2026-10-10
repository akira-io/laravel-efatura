<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\IudData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\IudSegment;
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
                'repositoryCode'   => (int) IudSegment::Repository->of($iud),
                'issueDate'        => '20' . implode('-', str_split(IudSegment::IssueDate->of($iud), 2)),
                'emitterTaxId'     => IudSegment::EmitterTaxId->of($iud),
                'ledCode'          => (int) IudSegment::LedCode->of($iud),
                'documentTypeCode' => self::documentType((int) IudSegment::DocumentType->of($iud)),
                'documentNumber'   => (int) IudSegment::DocumentNumber->of($iud),
                'randomCode'       => IudSegment::RandomCode->of($iud),
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
