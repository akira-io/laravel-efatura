<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\DocumentType;
use Spatie\LaravelData\Attributes\DataCollectionOf;

final class SalesReceiptData extends InvoiceData
{
    /**
     * @param list<LineItemData> $lines
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        #[DataCollectionOf(LineItemData::class)]
        public readonly array $lines,
        public readonly TotalsData $totals,
        public readonly PaymentsData $payments,
        public readonly ?PartyData $receiver = null,
        public readonly ?DeliveryData $delivery = null,
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {
        $this->validateDocument();
    }

    public function type(): DocumentType
    {
        return DocumentType::SalesReceipt;
    }
}
