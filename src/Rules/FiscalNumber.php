<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Money\DecimalFormatter;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class FiscalNumber implements ValidationRule
{
    public function __construct(
        private int $scale = 5,
        private bool $positive = false,
        private ?string $maximum = null,
        private ?string $currency = null,
        private bool $signed = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof Money) {
            if ($this->currency === null || $value->getCurrency()->getCurrencyCode() !== $this->currency) {
                $fail('efatura::efatura.validation.currency_mismatch')->translate();

                return;
            }

            $value = $value->getAmount();
        } elseif ($value instanceof BigDecimal && $this->currency !== null) {
            $fail('efatura::efatura.validation.invalid_money')->translate();

            return;
        }

        if (! \is_int($value) && ! \is_string($value) && ! $value instanceof BigDecimal) {
            $fail('efatura::efatura.validation.invalid_decimal')->translate();

            return;
        }

        try {
            $decimal = DecimalFormatter::parse($value, $this->scale);
        } catch (EfaturaValidationException) {
            $fail('efatura::efatura.validation.invalid_decimal')->translate();

            return;
        }

        if ((! $this->signed && $decimal->isNegative()) || ($this->positive && $decimal->isZero())
            || ($this->maximum !== null && $decimal->isGreaterThan($this->maximum))) {
            $fail('efatura::efatura.validation.number_bounds')->translate();
        }
    }
}
