<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Data\Attributes\CveAmount;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class TotalsData extends Data
{
    use ValidatesFiscalFields;

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
        #[DataCollectionOf(PayableAlternativeAmountData::class)]
        public readonly array $payableAlternativeAmounts = [],
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'priceExtensionTotalAmount' => ['required', FiscalNumber::amount(Fiscal::CURRENCY)],
            'netTotalAmount'            => ['required', FiscalNumber::amount(Fiscal::CURRENCY)],
            'taxTotalAmount'            => ['required', FiscalNumber::amount(Fiscal::CURRENCY)],
            'payableAmount'             => ['required', FiscalNumber::amount(Fiscal::CURRENCY)],
            'chargeTotalAmount'         => ['nullable', FiscalNumber::amount(Fiscal::CURRENCY)],
            'discountTotalAmount'       => ['nullable', FiscalNumber::amount(Fiscal::CURRENCY)],
            'withholdingTaxTotalAmount' => ['nullable', FiscalNumber::amount(Fiscal::CURRENCY)],
            'payableRoundingAmount'     => ['nullable', FiscalNumber::signedAmount(Fiscal::CURRENCY)],
            'payableAlternativeAmounts' => ['array', 'list'],
        ];
    }
}
