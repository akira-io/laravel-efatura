<?php

declare(strict_types=1);

namespace Akira\Efatura\Concerns;

use Akira\Efatura\Enums\DocumentType;

use function is_string;

trait ValidatesInvoiceType
{
    private static function ensureInvoiceType(\Illuminate\Contracts\Validation\Validator $validator, DocumentType $expected, mixed $value, string $path): void
    {
        $documentType = self::normalizeDocumentType($value);

        if ($documentType !== $expected) {
            $validator->errors()->add($path, __('efatura.validation.invoice_type_mismatch'));
        }
    }

    private static function normalizeDocumentType(mixed $value): ?DocumentType
    {
        if ($value instanceof DocumentType) {
            return $value;
        }

        if (is_string($value)) {
            return DocumentType::tryFrom($value);
        }

        return null;
    }
}
