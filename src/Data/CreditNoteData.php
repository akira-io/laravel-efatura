<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Support\DocumentRules;
use Akira\Efatura\Support\Fiscal;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreditNoteData extends DocumentData implements HasTotals
{
    /**
     * @param list<LineItemData>  $lines
     * @param list<ReferenceData> $references
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        public readonly PartyData $receiver,
        #[DataCollectionOf(LineItemData::class), ListType, Min(1), Max(Fiscal::MAX_LINES)]
        public readonly array $lines,
        public readonly TotalsData $totals,
        #[MapName('issueReasonCode')]
        public readonly IssueReason $issueReason,
        #[DataCollectionOf(ReferenceData::class), ListType, Max(Fiscal::MAX_REFERENCES)]
        public readonly array $references,
        public readonly ?DatePeriodData $rappelPeriod = null,
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
