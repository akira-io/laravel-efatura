<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesInvoiceType;
use Akira\Efatura\Enums\DocumentType;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;

final class ElectronicInvoiceData extends Data
{
    use ValidatesInvoiceType;

    public const DocumentType TYPE = DocumentType::ELECTRONIC_INVOICE;

    public function __construct(
        public readonly InvoiceData $invoice,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->after(static function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = data_get($validator->getData(), 'invoice.type');

            self::ensureInvoiceType($validator, DocumentType::ELECTRONIC_INVOICE, $type, 'invoice.type');
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'invoice' => ['bail', 'required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'invoice.required' => __('efatura.validation.invoice_required'),
            'invoice.array'    => __('efatura.validation.invoice_required'),
        ];
    }

    public static function stopOnFirstFailure(): bool
    {
        return true;
    }
}
