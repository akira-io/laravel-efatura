<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Brick\Money\Money;
use Illuminate\Validation\ValidationException;
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
            throw ValidationException::withMessages([$property->name => __('efatura::efatura.validation.invalid_money_input')]);
        }

        if (! $this->round && ! DecimalFormatter::fitsScale($value->getAmount(), $this->scale)) {
            throw ValidationException::withMessages([$property->name => __('efatura::efatura.validation.decimal_scale_exceeded')]);
        }

        return DecimalFormatter::money($value, $this->scale, $this->round, $property->name);
    }
}
