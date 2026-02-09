<?php

declare(strict_types=1);

namespace Akira\Efatura\Concerns;

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Support\Trans;
use Illuminate\Contracts\Validation\Validator;

trait ValidatesInvoiceType
{
    private static function ensureInvoiceType(Validator $validator, DocumentType $expected, mixed $value, string $path): void
    {
        $documentType = self::normalizeDocumentType($value);

        if ($documentType !== $expected) {
            $validator->errors()->add($path, Trans::get('efatura.validation.invoice_type_mismatch'));
        }
    }

    private static function normalizeDocumentType(mixed $value): ?DocumentType
    {
        if ($value instanceof DocumentType) {
            return $value;
        }

        if (\is_string($value)) {
            return DocumentType::tryFrom($value);
        }

        return null;
    }
}
