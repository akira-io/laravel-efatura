<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\IssueReason;
use Spatie\LaravelData\Attributes\DataCollectionOf;

final class CreditNoteData extends InvoiceData
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
        public readonly IssueReason $issueReasonCode,
        #[DataCollectionOf(ReferenceData::class)]
        public readonly array $references,
        public readonly ?DatePeriodData $rappelPeriod = null,
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {
        $this->validateDocument();
    }

    public function type(): DocumentType
    {
        return DocumentType::CreditNote;
    }
}
