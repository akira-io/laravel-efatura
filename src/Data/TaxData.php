<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\StampTaxCode;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\BigDecimalCast;
use Akira\Efatura\Money\BigDecimalTransformer;
use Akira\Efatura\Money\MoneyCast;
use Akira\Efatura\Money\MoneyTransformer;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class TaxData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly TaxType $taxTypeCode,
        #[WithCast(BigDecimalCast::class, 3)]
        #[WithTransformer(BigDecimalTransformer::class, 3)]
        public readonly ?BigDecimal $taxPercentage = null,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly ?Money $taxAmount = null,
        public readonly ?string $taxExemptionReasonCode = null,
        public readonly ?StampTaxCode $stampTaxCode = null,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly ?Money $taxTotal = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'taxTypeCode'            => ['required', Rule::enum(TaxType::class)],
            'taxPercentage'          => ['nullable', 'required_without_all:taxAmount,taxExemptionReasonCode', 'prohibits:taxAmount,taxExemptionReasonCode', new FiscalNumber(3, true, '100')],
            'taxAmount'              => ['nullable', 'prohibits:taxPercentage,taxExemptionReasonCode', new FiscalNumber(positive: true, currency: 'CVE')],
            'taxExemptionReasonCode' => ['nullable', 'required_if:taxTypeCode,NA', 'prohibits:taxPercentage,taxAmount', new OfficialCode('tax_exemption_reasons')],
            'stampTaxCode'           => ['nullable', 'required_if:taxTypeCode,IS', Rule::enum(StampTaxCode::class)],
            'taxTotal'               => ['nullable', new FiscalNumber(positive: true, currency: 'CVE')],
        ];
    }
}
