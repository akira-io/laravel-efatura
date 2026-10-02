<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\DiscountValueCast;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\ValidationPayload;
use Akira\Efatura\Transformers\DiscountValueTransformer;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DiscountData extends FiscalData
{
    public function __construct(
        #[WithCast(DiscountValueCast::class)]
        #[WithTransformer(DiscountValueTransformer::class)]
        public readonly Money|BigDecimal $value,
        public readonly DiscountValueType $valueType = DiscountValueType::Percentage,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $type = ValidationPayload::enum($context, 'valueType', DiscountValueType::class, DiscountValueType::Percentage);

        return ['value' => [$type === DiscountValueType::Amount ? FiscalNumber::amount(Fiscal::CURRENCY) : FiscalNumber::nonNegative(maximum: '100')]];
    }
}
