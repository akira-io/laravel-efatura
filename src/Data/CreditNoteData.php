<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesInvoiceType;
use Akira\Efatura\Enums\DocumentType;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;

final class CreditNoteData extends Data
{
    use ValidatesInvoiceType;

    public const DocumentType TYPE = DocumentType::CreditNote;

    public function __construct(
        public readonly InvoiceData $invoice,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'invoice'                  => ['bail', 'required', 'array'],
            'invoice.originalIud'      => ['bail', 'required', 'string'],
            'invoice.creditNoteReason' => ['bail', 'required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'invoice.required'                  => __('efatura.validation.invoice_required'),
            'invoice.array'                     => __('efatura.validation.invoice_required'),
            'invoice.originalIud.required'      => __('efatura.invoice.original_iud_required'),
            'invoice.creditNoteReason.required' => __('efatura.invoice.credit_note_reason_required'),
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(static function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = data_get($validator->getData(), 'invoice.type');

            self::ensureInvoiceType($validator, DocumentType::CreditNote, $type, 'invoice.type');
        });
    }

    public static function stopOnFirstFailure(): bool
    {
        return true;
    }
}
