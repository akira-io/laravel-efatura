<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;

final class ReceiptInvoiceData extends InvoiceData
{
    /**
     * @param list<LineItemData>  $lines
     * @param list<ReferenceData> $references
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        public readonly PartyData $receiver,
        #[DataCollectionOf(LineItemData::class)]
        public readonly array $lines,
        public readonly TotalsData $totals,
        public readonly PaymentsData $payments,
        public readonly ?string $orderReference = null,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $taxPointDate = null,
        public readonly ?PartyData $paymentParty = null,
        #[DataCollectionOf(ReferenceData::class)]
        public readonly array $references = [],
        public readonly ?DeliveryData $delivery = null,
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {
        $this->validateDocument();
    }

    public function type(): DocumentType
    {
        return DocumentType::InvoiceReceipt;
    }
}
