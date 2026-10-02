<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Support\FiscalRules;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Min;

final class ReturnNoteData extends InvoiceData
{
    /**
     * @param list<LineItemData>  $lines
     * @param list<ReferenceData> $references
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        #[DataCollectionOf(LineItemData::class), ListType, Min(1)]
        public readonly array $lines,
        public readonly TotalsData $totals,
        public readonly IssueReason $issueReasonCode,
        #[DataCollectionOf(ReferenceData::class), ListType]
        public readonly array $references,
        public readonly ?PartyData $receiver = null,
        public readonly ?string $issueReasonDescription = null,
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {}

    public function type(): DocumentType
    {
        return DocumentType::ReturnNote;
    }

    /**
     * @return array<string, list<mixed>>
     */
    #[Override]
    protected static function documentRules(): array
    {
        return ['issueReasonDescription' => FiscalRules::text(10, 500)];
    }
}
