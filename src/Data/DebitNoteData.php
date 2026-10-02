<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Support\DocumentRules;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DebitNoteData extends InvoiceData implements HasTotals
{
    /**
     * @param list<LineItemData>  $lines
     * @param list<ReferenceData> $references
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        public readonly PartyData $receiver,
        #[DataCollectionOf(LineItemData::class), ListType, Min(1)]
        public readonly array $lines,
        public readonly TotalsData $totals,
        public readonly IssueReason $issueReasonCode,
        #[DataCollectionOf(ReferenceData::class), ListType]
        public readonly array $references,
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    #[Override]
    protected static function documentRules(ValidationContext $context): array
    {
        return [
            ...DocumentRules::lines($context, self::documentType()),
            'references'      => DocumentRules::requiredList(),
            'issueReasonCode' => DocumentRules::issueReason(self::documentType()),
        ];
    }
}
