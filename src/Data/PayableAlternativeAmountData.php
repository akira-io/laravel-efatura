<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\BigDecimalCast;
use Akira\Efatura\Casts\ForeignMoneyCast;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Support\ValidationPayload;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCastAndTransformer;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PayableAlternativeAmountData extends FiscalData
{
    public function __construct(
        #[WithCastAndTransformer(ForeignMoneyCast::class)]
        public readonly Money $value,
        public readonly string $currencyCode,
        #[WithCastAndTransformer(BigDecimalCast::class)]
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
