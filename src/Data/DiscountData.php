<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\DiscountValueCast;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\ValidationPayload;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCastAndTransformer;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DiscountData extends FiscalData
{
    public function __construct(
        #[WithCastAndTransformer(DiscountValueCast::class)]
        public readonly Money|BigDecimal $value,
        public readonly DiscountValueType $valueType = DiscountValueType::Percentage,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $type = ValidationPayload::enum($context, 'valueType', DiscountValueType::class, DiscountValueType::Percentage);

        $value = $type === DiscountValueType::Amount ? FiscalNumber::amount(Fiscal::CURRENCY) : FiscalNumber::nonNegative(Fiscal::PERCENTAGE_SCALE, '100');

        return ['value' => [$value]];
    }
}
