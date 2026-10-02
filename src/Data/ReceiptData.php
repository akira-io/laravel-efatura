<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\ReceiptType;
use Akira\Efatura\Support\DocumentRules;
use Akira\Efatura\Support\ValidationPayload;
use Illuminate\Validation\Rule;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ReceiptData extends InvoiceData
{
    /**
     * @param list<ReferenceData> $references
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        public readonly PartyData $receiver,
        public readonly ReceiptType $receiptTypeCode,
        #[DataCollectionOf(ReferenceData::class), ListType]
        public readonly array $references,
        public readonly PaymentsData $payments,
        public readonly ?PartyData $paymentParty = null,
        public readonly ?RentReceiptData $rentReceipt = null,
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {}

    public function type(): DocumentType
    {
        return DocumentType::Receipt;
    }

    /**
     * @return array<string, list<mixed>>
     */
    #[Override]
    protected static function documentRules(ValidationContext $context): array
    {
        $rent = ValidationPayload::enum($context, 'receiptTypeCode', ReceiptType::class) === ReceiptType::Rent;

        return [
            ...DocumentRules::settledPayments($context),
            'references'  => DocumentRules::requiredList(),
            'rentReceipt' => [Rule::requiredIf($rent), Rule::prohibitedIf(! $rent)],
        ];
    }
}
