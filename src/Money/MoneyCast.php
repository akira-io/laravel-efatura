<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Brick\Money\Money;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final readonly class MoneyCast implements Cast
{
    public function __construct(
        private string $currency,
        private int $scale = 2,
        private bool $round = true,
    ) {
        if ($this->round && $this->scale !== 2) {
            throw new EfaturaValidationException('scale', __('efatura::efatura.validation.invalid_money_rounding_scale'));
        }
    }

    /**
     * @param array<string, mixed>  $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Money
    {
        if (! \is_int($value) && ! \is_string($value) && ! $value instanceof Money) {
            throw new EfaturaValidationException($property->name, __('efatura::efatura.validation.invalid_money_input'));
        }

        if ($this->round) {
            return FiscalMoney::of($value, $this->currency);
        }

        return FiscalMoney::exact($value, $this->currency, $this->scale);
    }
}
