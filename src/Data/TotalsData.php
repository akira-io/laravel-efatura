<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\CveAmount;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;

final class TotalsData extends FiscalData
{
    private const array UNSIGNED_AMOUNTS = [
        'priceExtensionTotalAmount',
        'netTotalAmount',
        'taxTotalAmount',
        'payableAmount',
        'chargeTotalAmount',
        'discountTotalAmount',
        'withholdingTaxTotalAmount',
    ];

    /**
     * @param list<PayableAlternativeAmountData> $payableAlternativeAmounts
     */
    public function __construct(
        #[CveAmount]
        public readonly Money $priceExtensionTotalAmount,
        #[CveAmount]
        public readonly Money $netTotalAmount,
        #[CveAmount]
        public readonly Money $taxTotalAmount,
        #[CveAmount]
        public readonly Money $payableAmount,
        #[CveAmount]
        public readonly ?Money $chargeTotalAmount = null,
        #[CveAmount]
        public readonly ?Money $discountTotalAmount = null,
        #[CveAmount]
        public readonly ?Money $withholdingTaxTotalAmount = null,
        #[CveAmount]
        public readonly ?Money $payableRoundingAmount = null,
        public readonly ?DiscountData $discount = null,
        #[DataCollectionOf(PayableAlternativeAmountData::class), ListType]
        public readonly array $payableAlternativeAmounts = [],
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            ...collect(self::UNSIGNED_AMOUNTS)
                ->mapWithKeys(static fn (string $field): array => [$field => [FiscalNumber::amount(Fiscal::CURRENCY)]])->all(),
            'payableRoundingAmount' => [FiscalNumber::signedAmount(Fiscal::CURRENCY)],
        ];
    }
}
