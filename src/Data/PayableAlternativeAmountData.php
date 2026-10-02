<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Money\BigDecimalCast;
use Akira\Efatura\Money\BigDecimalTransformer;
use Akira\Efatura\Money\ForeignMoneyCast;
use Akira\Efatura\Money\MoneyTransformer;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class PayableAlternativeAmountData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        #[WithCast(ForeignMoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly Money $value,
        public readonly string $currencyCode,
        #[WithCast(BigDecimalCast::class)]
        #[WithTransformer(BigDecimalTransformer::class)]
        public readonly BigDecimal $exchangeRate,
    ) {
        $this->validateFiscalFields([...self::rules(), 'value' => ['required', FiscalNumber::amount($currencyCode)]]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'currencyCode' => ['required', new OfficialCode(Catalog::Currencies)],
            'exchangeRate' => ['required', FiscalNumber::positive()],
        ];
    }
}
