<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Enums\StampTaxCode;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\BigDecimalCast;
use Akira\Efatura\Money\BigDecimalTransformer;
use Akira\Efatura\Money\MoneyCast;
use Akira\Efatura\Money\MoneyTransformer;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class TaxData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly TaxType $taxTypeCode,
        #[WithCast(BigDecimalCast::class, 3)]
        #[WithTransformer(BigDecimalTransformer::class, 3)]
        public readonly ?BigDecimal $taxPercentage = null,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly ?Money $taxAmount = null,
        public readonly ?string $taxExemptionReasonCode = null,
        public readonly ?StampTaxCode $stampTaxCode = null,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly ?Money $taxTotal = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $field = static fn (string $name): string => $context?->path->property($name)->get() ?? $name;

        return [
            'taxTypeCode'            => ['required', Rule::enum(TaxType::class)],
            'taxPercentage'          => ['nullable', 'required_without_all:' . $field('taxAmount') . ',' . $field('taxExemptionReasonCode'), 'prohibits:' . $field('taxAmount') . ',' . $field('taxExemptionReasonCode'), new FiscalNumber(3, true, '100')],
            'taxAmount'              => ['nullable', 'prohibits:' . $field('taxPercentage') . ',' . $field('taxExemptionReasonCode'), new FiscalNumber(positive: true, currency: Fiscal::CURRENCY)],
            'taxExemptionReasonCode' => ['nullable', 'required_if:' . $field('taxTypeCode') . ',NA', 'prohibits:' . $field('taxPercentage') . ',' . $field('taxAmount'), new OfficialCode(Catalog::TaxExemptionReasons)],
            'stampTaxCode'           => ['nullable', 'required_if:' . $field('taxTypeCode') . ',IS', Rule::enum(StampTaxCode::class)],
            'taxTotal'               => ['nullable', new FiscalNumber(positive: true, currency: Fiscal::CURRENCY)],
        ];
    }
}
