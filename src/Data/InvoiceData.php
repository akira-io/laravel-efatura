<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\DocumentType;
use Spatie\LaravelData\Data;

use function is_array;
use function is_string;

final class InvoiceData extends Data
{
    /**
     * @param array<int, LineItemData> $lines
     */
    public function __construct(
        public readonly DocumentType $type,
        public readonly string $issueDate,
        public readonly PartyData $emitter,
        public readonly ?PartyData $receiver,
        public readonly array $lines,
        public readonly TotalsData $totals,
        public readonly ?string $originalIud = null,
        public readonly ?string $creditNoteReason = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'emitter'   => ['bail', 'required', 'array'],
            'issueDate' => ['bail', 'required', 'string'],
            'lines'     => ['bail', 'required', 'array', 'min:1'],
            'receiver'  => ['bail', 'nullable', 'array'],
            'totals'    => ['bail', 'required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'emitter.required'   => __('efatura.validation.emitter_required'),
            'emitter.array'      => __('efatura.validation.emitter_required'),
            'issueDate.required' => __('efatura.invoice.issue_date_required'),
            'lines.required'     => __('efatura.validation.lines_required'),
            'lines.min'          => __('efatura.validation.lines_required'),
            'receiver.array'     => __('efatura.validation.receiver_required'),
            'totals.required'    => __('efatura.validation.totals_required'),
            'totals.array'       => __('efatura.validation.totals_required'),
        ];
    }

    public static function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(static function (\Illuminate\Contracts\Validation\Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data     = $validator->getData();
            $type     = data_get($data, 'type');
            $receiver = data_get($data, 'receiver');
            $lines    = data_get($data, 'lines');

            if ($type instanceof DocumentType) {
                $documentType = $type;
            } elseif (is_string($type)) {
                $documentType = DocumentType::tryFrom($type);
            } else {
                return;
            }

            if ($documentType === null) {
                return;
            }

            if ($documentType === DocumentType::SALES_RECEIPT) {
                return;
            }

            if (! is_array($lines) || $lines === []) {
                $validator->errors()->add('lines', __('efatura.validation.lines_required'));

                return;
            }

            if ($receiver === null) {
                $validator->errors()->add('receiver', __('efatura.invoice.receiver_required_for_type'));

                return;
            }

            if (! is_array($receiver)) {
                $validator->errors()->add('receiver', __('efatura.validation.receiver_required'));
            }
        });
    }

    public static function stopOnFirstFailure(): bool
    {
        return true;
    }
}
