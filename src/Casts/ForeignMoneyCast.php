<?php

declare(strict_types=1);

namespace Akira\Efatura\Casts;

use Akira\Efatura\Money\CatalogCurrency;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Transformers\MoneyTransformer;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final readonly class ForeignMoneyCast implements Cast, Transformer
{
    /**
     * @param array<string, mixed>  $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Money
    {
        $currency = $properties['currencyCode'] ?? null;

        if (! \is_string($currency) || ! CatalogCurrency::isOfficial($currency)) {
            throw ValidationException::withMessages([FieldPath::of($context, 'currencyCode') => __('efatura::efatura.validation.invalid_currency')]);
        }

        return new MoneyCast(CatalogCurrency::of($currency), Fiscal::AMOUNT_SCALE, false)->cast($property, $value, $properties, $context);
    }

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        return new MoneyTransformer(Fiscal::AMOUNT_SCALE, false)->transform($property, $value, $context);
    }
}
