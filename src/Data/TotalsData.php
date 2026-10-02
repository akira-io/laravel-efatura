<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Money\MoneyCast;
use Akira\Efatura\Money\MoneyTransformer;
use Akira\Efatura\Rules\DataInstances;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class TotalsData extends Data
{
    use ValidatesFiscalFields;

    /**
     * @param list<PayableAlternativeAmountData> $payableAlternativeAmounts
     */
    public function __construct(
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly Money $priceExtensionTotalAmount,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly Money $netTotalAmount,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly Money $taxTotalAmount,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly Money $payableAmount,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly ?Money $chargeTotalAmount = null,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly ?Money $discountTotalAmount = null,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly ?Money $withholdingTaxTotalAmount = null,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly ?Money $payableRoundingAmount = null,
        public readonly ?DiscountData $discount = null,
        public readonly array $payableAlternativeAmounts = [],
    ) {
        $rules                                = self::rules();
        $rules['payableAlternativeAmounts'][] = new DataInstances(PayableAlternativeAmountData::class);
        $this->validateFiscalFields($rules);
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
