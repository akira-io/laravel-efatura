<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Actions\ValidateDocumentCompatibilityAction;
use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Rules\DataInstances;
use Akira\Efatura\Support\FiscalRules;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Override;
use Spatie\LaravelData\Data;

abstract class InvoiceData extends Data
{
    use ValidatesFiscalFields;

    abstract public DocumentHeaderData $header { get; }

    abstract public PartyData $emitter { get; }

    abstract public ?PartyData $receiver { get; }

    abstract public ?EmissionContextData $emission { get; }

    abstract public function type(): DocumentType;

    /** @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    #[Override]
    final public static function prepareForPipeline(array $properties): array
    {
        foreach (['lines', 'totals', 'references', 'payments', 'paymentParty', 'delivery', 'dueDate', 'taxPointDate', 'orderReference',
            'issueReasonCode', 'issueReasonDescription', 'rappelPeriod', 'receiptTypeCode', 'rentReceipt', 'receiverTypeCode',
            'transportDocumentTypeCode', 'transportServiceProvider', 'transportRoute'] as $field) {
            if (! property_exists(static::class, $field) && Arr::has($properties, $field)) {
                throw ValidationException::withMessages([$field => __('efatura::efatura.validation.document_field_forbidden')]);
            }
        }

        static::validate($properties);

        return $properties;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    final public static function rules(): array
    {
        return ['receiver' => [\in_array(static::class, [SalesReceiptData::class, ReturnNoteData::class, TransportDocumentData::class], true) ? 'nullable' : 'required'], 'emission' => ['nullable'], 'footer' => ['nullable'],
            'payments'     => [\in_array(static::class, [ReceiptInvoiceData::class, SalesReceiptData::class, ReceiptData::class], true) ? 'required' : 'nullable'], 'paymentParty' => ['nullable'], 'delivery' => ['nullable'], 'rappelPeriod' => ['nullable'], 'rentReceipt' => ['nullable']];
    }

    protected function validateDocument(): void
    {
        $rules = [];
        if (property_exists($this, 'lines')) {
            $rules['lines'] = ['required', 'array', 'list', 'min:1', new DataInstances(LineItemData::class)];
        }

        if (property_exists($this, 'references')) {
            $rules['references'] = ['array', 'list', new DataInstances(ReferenceData::class)];
        }

        if (property_exists($this, 'orderReference')) {
            $rules['orderReference'] = ['nullable', ...FiscalRules::code()];
        }

        if (property_exists($this, 'issueReasonDescription')) {
            $rules['issueReasonDescription'] = ['nullable', ...FiscalRules::text(10, 500)];
        }

        $this->validateFiscalFields($rules);
        resolve(ValidateDocumentCompatibilityAction::class)->handle($this);
    }
}
