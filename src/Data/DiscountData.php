<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Money\DiscountValueCast;
use Akira\Efatura\Money\DiscountValueTransformer;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class DiscountData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        #[WithCast(DiscountValueCast::class)]
        #[WithTransformer(DiscountValueTransformer::class)]
        public readonly Money|BigDecimal $value,
        public readonly DiscountValueType $valueType = DiscountValueType::Percentage,
    ) {
        $this->validateFiscalFields(['value' => ['required', new FiscalNumber(
            maximum: $valueType === DiscountValueType::Percentage ? '100' : null,
            currency: $valueType === DiscountValueType::Amount ? Fiscal::CURRENCY : null,
        )]]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['value' => ['required']];
    }
}
