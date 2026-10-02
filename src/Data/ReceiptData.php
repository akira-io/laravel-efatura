<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\ReceiptType;
use Spatie\LaravelData\Attributes\DataCollectionOf;

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
        #[DataCollectionOf(ReferenceData::class)]
        public readonly array $references,
        public readonly PaymentsData $payments,
        public readonly ?PartyData $paymentParty = null,
        public readonly ?RentReceiptData $rentReceipt = null,
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {
        $this->validateDocument();
    }

    public function type(): DocumentType
    {
        return DocumentType::Receipt;
    }
}
