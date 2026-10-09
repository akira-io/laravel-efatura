<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Support\DocumentRuleSets;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\ValidationPayload;
use Brick\Math\BigDecimal;
use Illuminate\Validation\Rule;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class SalesReceiptData extends DocumentData implements HasTotals
{
    /**
     * @param list<LineItemData> $lines
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        #[DataCollectionOf(LineItemData::class), ListType, Min(1), Max(Fiscal::MAX_LINES)]
        public readonly array $lines,
        public readonly TotalsData $totals,
        public readonly PaymentsData $payments,
        public readonly ?PartyData $receiver = null,
        public readonly ?DeliveryData $delivery = null,
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    #[Override]
    protected static function documentRules(ValidationContext $context): array
    {
        $net              = ValidationPayload::decimal($context, 'totals.netTotalAmount');
        $tax              = ValidationPayload::decimal($context, 'totals.taxTotalAmount');
        $identifiedAmount = $net instanceof BigDecimal && $tax instanceof BigDecimal ? $net->plus($tax) : null;

        return [
            ...DocumentRuleSets::lines($context, self::documentType()),
            ...DocumentRuleSets::settledPayments($context),
            'receiver' => [Rule::requiredIf($identifiedAmount?->isGreaterThanOrEqualTo(Fiscal::SALES_RECEIPT_IDENTIFIED_RECEIVER_AMOUNT) ?? false)],
        ];
    }
}
