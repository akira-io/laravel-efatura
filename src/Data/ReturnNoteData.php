<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Support\DocumentRuleSets;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Support\ValidationPayload;
use Illuminate\Validation\Rule;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ReturnNoteData extends DocumentData implements HasTotals
{
    /**
     * @param list<LineItemData>  $lines
     * @param list<ReferenceData> $references
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        #[DataCollectionOf(LineItemData::class), ListType, Min(1), Max(Fiscal::MAX_LINES)]
        public readonly array $lines,
        public readonly TotalsData $totals,
        #[MapName('issueReasonCode')]
        public readonly IssueReason $issueReason,
        #[DataCollectionOf(ReferenceData::class), ListType, Max(Fiscal::MAX_REFERENCES)]
        public readonly array $references,
        public readonly ?PartyData $receiver = null,
        public readonly ?string $issueReasonDescription = null,
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    #[Override]
    protected static function documentRules(ValidationContext $context): array
    {
        $other = ValidationPayload::enum($context, 'issueReasonCode', IssueReason::class) === IssueReason::Other;

        return [
            ...DocumentRuleSets::lines($context, self::documentType()),
            'references'             => DocumentRuleSets::requiredList(),
            'issueReasonCode'        => DocumentRuleSets::issueReason(self::documentType()),
            'issueReasonDescription' => [Rule::requiredIf($other), ...FiscalRules::text(10, 500)],
        ];
    }
}
