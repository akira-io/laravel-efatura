<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;

final class ElectronicInvoiceData extends InvoiceData
{
    /**
     * @param list<LineItemData>  $lines
     * @param list<ReferenceData> $references
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        public readonly PartyData $receiver,
        public readonly array $lines,
        public readonly TotalsData $totals,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $dueDate = null,
        public readonly ?string $orderReference = null,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $taxPointDate = null,
        public readonly array $references = [],
        public readonly ?PaymentsData $payments = null,
        public readonly ?DeliveryData $delivery = null,
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {
        $this->validateDocument();
    }

    public function type(): DocumentType
    {
        return DocumentType::Invoice;
    }
}
