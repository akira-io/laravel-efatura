<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final readonly class BigDecimalTransformer implements Transformer
{
    public function __construct(private int $maxScale = Fiscal::AMOUNT_SCALE) {}

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        if (! $value instanceof BigDecimal) {
            throw ValidationException::withMessages([$property->name => __('efatura::efatura.validation.invalid_decimal')]);
        }

        if (! DecimalFormatter::fitsScale($value, $this->maxScale)) {
            throw ValidationException::withMessages([$property->name => __('efatura::efatura.validation.decimal_scale_exceeded')]);
        }

        return DecimalFormatter::decimal($value, $this->maxScale, $property->name);
    }
}
