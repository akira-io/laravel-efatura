<?php

declare(strict_types=1);

namespace Akira\Efatura\Transformers;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Money\DecimalFormatter;
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
        $field = $property->name;

        if (! $value instanceof Money) {
            throw ValidationException::withMessages([$field => __('efatura::efatura.validation.invalid_money_input')]);
        }

        try {
            return DecimalFormatter::money($value, $this->scale, $this->round, $field);
        } catch (EfaturaValidationException $efaturaValidationException) {
            throw ValidationException::withMessages([$field => $efaturaValidationException->getMessage()]);
        }
    }
}
