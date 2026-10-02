<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\CveAmount;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Enums\StampTaxCode;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\BigDecimalCast;
use Akira\Efatura\Money\BigDecimalTransformer;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\NotBlank;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Support\ValidationPayload;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class TaxData extends FiscalData
{
    public function __construct(
        public readonly TaxType $taxTypeCode,
        #[WithCast(BigDecimalCast::class, 3)]
        #[WithTransformer(BigDecimalTransformer::class, 3)]
        public readonly ?BigDecimal $taxPercentage = null,
        #[CveAmount]
        public readonly ?Money $taxAmount = null,
        public readonly ?string $taxExemptionReasonCode = null,
        public readonly ?StampTaxCode $stampTaxCode = null,
        #[CveAmount]
        public readonly ?Money $taxTotal = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context, Catalogs $catalogs): array
    {
        $type = ValidationPayload::enum($context, 'taxTypeCode', TaxType::class);

        return [
            ...FiscalRules::exactlyOneOf($context, [
                'taxPercentage'          => [FiscalNumber::positive(3, '100')],
                'taxAmount'              => [FiscalNumber::positiveAmount(Fiscal::CURRENCY)],
                'taxExemptionReasonCode' => [new NotBlank, Rule::requiredIf($type === TaxType::NotApplicable), new OfficialCode(Catalog::TaxExemptionReasons, $catalogs)],
            ]),
            'stampTaxCode' => [Rule::requiredIf($type === TaxType::StampTax)],
            'taxTotal'     => [FiscalNumber::positiveAmount(Fiscal::CURRENCY)],
        ];
    }
}
