<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Brick\Money\Money;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final readonly class MoneyTransformer implements Transformer
{
    public function __construct(
        private int $scale = 2,
        private bool $round = true,
    ) {}

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        if (! $value instanceof Money) {
            throw new EfaturaValidationException($property->name, __('efatura.validation.invalid_money_input'));
        }

        return DecimalFormatter::money($value, $this->scale, $this->round);
    }
}
