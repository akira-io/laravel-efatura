<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Money\MoneyCast;
use Akira\Efatura\Money\MoneyTransformer;
use Akira\Efatura\Rules\DataInstances;
use Akira\Efatura\Rules\FiscalNumber;
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
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly Money $priceExtensionTotalAmount,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly Money $netTotalAmount,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly Money $taxTotalAmount,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly Money $payableAmount,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly ?Money $chargeTotalAmount = null,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly ?Money $discountTotalAmount = null,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly ?Money $withholdingTaxTotalAmount = null,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
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
            'priceExtensionTotalAmount' => ['required', new FiscalNumber(currency: 'CVE')],
            'netTotalAmount'            => ['required', new FiscalNumber(currency: 'CVE')],
            'taxTotalAmount'            => ['required', new FiscalNumber(currency: 'CVE')],
            'payableAmount'             => ['required', new FiscalNumber(currency: 'CVE')],
            'chargeTotalAmount'         => ['nullable', new FiscalNumber(currency: 'CVE')],
            'discountTotalAmount'       => ['nullable', new FiscalNumber(currency: 'CVE')],
            'withholdingTaxTotalAmount' => ['nullable', new FiscalNumber(currency: 'CVE')],
            'payableRoundingAmount'     => ['nullable', new FiscalNumber(currency: 'CVE', signed: true)],
            'payableAlternativeAmounts' => ['array', 'list'],
        ];
    }
}
