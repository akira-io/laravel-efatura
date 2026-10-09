<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Data\Contracts\HasTaxPointDate;
use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Support\DocumentRules;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Carbon\CarbonImmutable;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ElectronicInvoiceData extends DocumentData implements HasTaxPointDate, HasTotals
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
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $dueDate = null,
        public readonly ?string $orderReference = null,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $taxPointDate = null,
        #[DataCollectionOf(ReferenceData::class), ListType]
        public readonly array $references = [],
        public readonly ?PaymentsData $payments = null,
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
        return [
            ...DocumentRules::lines($context, self::documentType()),
            'orderReference'    => FiscalRules::code(),
            'payments.payments' => ['bail', 'prohibited'],
        ];
    }
}
