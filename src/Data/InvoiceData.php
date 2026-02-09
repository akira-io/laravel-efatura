<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Contracts\DocumentTypePolicy;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Support\Trans;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;

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
            'emitter.required'   => Trans::get('efatura.validation.emitter_required'),
            'emitter.array'      => Trans::get('efatura.validation.emitter_required'),
            'issueDate.required' => Trans::get('efatura.invoice.issue_date_required'),
            'lines.required'     => Trans::get('efatura.validation.lines_required'),
            'lines.min'          => Trans::get('efatura.validation.lines_required'),
            'receiver.array'     => Trans::get('efatura.validation.receiver_required'),
            'totals.required'    => Trans::get('efatura.validation.totals_required'),
            'totals.array'       => Trans::get('efatura.validation.totals_required'),
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(static function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data     = $validator->getData();
            $type     = data_get($data, 'type');
            $receiver = data_get($data, 'receiver');
            $lines    = data_get($data, 'lines');

            if ($type instanceof DocumentType) {
                $documentType = $type;
            } elseif (\is_string($type)) {
                $documentType = DocumentType::tryFrom($type);
            } else {
                return;
            }

            if ($documentType === null) {
                return;
            }

            $policy = resolve(DocumentTypePolicy::class);

            if (! $policy->supportsEmission($documentType)) {
                $validator->errors()->add('type', Trans::get('efatura.invoice.document_type_not_supported', [
                    'type' => $documentType->value,
                ]));

                return;
            }

            if ($documentType === DocumentType::ELECTRONIC_SALES_TICKET) {
                return;
            }

            if (! \is_array($lines) || $lines === []) {
                $validator->errors()->add('lines', Trans::get('efatura.validation.lines_required'));

                return;
            }

            if ($receiver === null) {
                $validator->errors()->add('receiver', Trans::get('efatura.invoice.receiver_required_for_type'));

                return;
            }

            if (! \is_array($receiver)) {
                $validator->errors()->add('receiver', Trans::get('efatura.validation.receiver_required'));
            }
        });
    }

    public static function stopOnFirstFailure(): bool
    {
        return true;
    }
}
