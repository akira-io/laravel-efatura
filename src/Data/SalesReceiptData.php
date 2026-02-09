<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesInvoiceType;
use Akira\Efatura\Enums\DocumentType;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;

final class SalesReceiptData extends Data
{
    use ValidatesInvoiceType;

    public const DocumentType TYPE = DocumentType::ELECTRONIC_SALES_TICKET;

    public function __construct(
        public readonly InvoiceData $invoice,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->after(static function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $validator->getData();
            $type = $data['invoice']['type'] ?? null;

            self::ensureInvoiceType($validator, DocumentType::ELECTRONIC_SALES_TICKET, $type, 'invoice.type');

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $total    = $data['invoice']['totals']['grandTotal'] ?? null;
            $receiver = $data['invoice']['receiver'] ?? null;

            if (is_numeric($total) && (float) $total >= 20000.0 && $receiver === null) {
                $validator->errors()->add('invoice.receiver', __('efatura.invoice.receiver_required_for_type'));

                return;
            }

            if (is_numeric($total) && (float) $total >= 20000.0 && ! \is_array($receiver)) {
                $validator->errors()->add('invoice.receiver', __('efatura.validation.receiver_required'));
            }
        });
    }

    public static function stopOnFirstFailure(): bool
    {
        return true;
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
}
