<?php

declare(strict_types=1);

namespace Akira\Efatura\Casts;

use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Transformers\BigDecimalTransformer;
use Akira\Efatura\Transformers\MoneyTransformer;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final readonly class DiscountValueCast implements Cast, Transformer
{
    /**
     * @param array<string, mixed>  $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Money|BigDecimal
    {
        $type = $properties['valueType'] ?? DiscountValueType::Percentage;

        return $type === DiscountValueType::Amount || $type === 'A'
            ? new MoneyCast(Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)->cast($property, $value, $properties, $context)
            : (new BigDecimalCast)->cast($property, $value, $properties, $context);
    }

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        return $value instanceof Money
            ? new MoneyTransformer(Fiscal::AMOUNT_SCALE, false)->transform($property, $value, $context)
            : (new BigDecimalTransformer)->transform($property, $value, $context);
    }
}
