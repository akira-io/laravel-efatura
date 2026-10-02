<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\BigDecimalCast;
use Akira\Efatura\Casts\ForeignMoneyCast;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\ValidationPayload;
use Akira\Efatura\Transformers\BigDecimalTransformer;
use Akira\Efatura\Transformers\MoneyTransformer;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PayableAlternativeAmountData extends FiscalData
{
    public function __construct(
        #[WithCast(ForeignMoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly Money $value,
        public readonly string $currencyCode,
        #[WithCast(BigDecimalCast::class)]
        #[WithTransformer(BigDecimalTransformer::class)]
        public readonly BigDecimal $exchangeRate,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context, Catalogs $catalogs): array
    {
        $currency = ValidationPayload::string($context, 'currencyCode');

        return [
            'value'        => [$currency === null ? FiscalNumber::nonNegative() : FiscalNumber::amount($currency)],
            'currencyCode' => [new OfficialCode(Catalog::Currencies, $catalogs)],
            'exchangeRate' => [FiscalNumber::positive()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return ['currencyCode.required' => __('efatura::efatura.validation.invalid_currency')];
    }
}
