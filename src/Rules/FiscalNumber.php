<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class FiscalNumber implements ValidationRule
{
    private ?BigDecimal $maximum;

    private function __construct(
        private int $scale,
        private bool $allowsZero,
        private bool $allowsNegative,
        ?string $maximum = null,
        private ?string $currency = null,
    ) {
        if ($scale < 0) {
            throw DefinitionException::negativeScale($scale);
        }

        if ($maximum !== null && (! DecimalFormatter::isPlainDecimal($maximum) || ! DecimalFormatter::fitsScale(BigDecimal::of($maximum), $scale))) {
            throw DefinitionException::numericBound($maximum, $scale);
        }

        $this->maximum = $maximum === null ? null : BigDecimal::of($maximum);
    }

    public static function positive(int $scale = Fiscal::AMOUNT_SCALE, ?string $maximum = null): self
    {
        return new self($scale, false, false, $maximum);
    }

    public static function nonNegative(int $scale = Fiscal::AMOUNT_SCALE, ?string $maximum = null): self
    {
        return new self($scale, true, false, $maximum);
    }

    public static function positiveAmount(string $currency): self
    {
        return new self(Fiscal::AMOUNT_SCALE, false, false, currency: $currency);
    }

    public static function amount(string $currency): self
    {
        return new self(Fiscal::AMOUNT_SCALE, true, false, currency: $currency);
    }

    public static function signedAmount(string $currency): self
    {
        return new self(Fiscal::AMOUNT_SCALE, true, true, currency: $currency);
    }

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

        if (! DecimalFormatter::isPlainDecimal($value)) {
            $fail('efatura::efatura.validation.invalid_decimal')->translate();

            return;
        }

        $decimal = BigDecimal::of($value);

        if (! DecimalFormatter::fitsScale($decimal, $this->scale)) {
            $fail('efatura::efatura.validation.decimal_scale_exceeded')->translate();

            return;
        }

        if (! $this->withinBounds($decimal)) {
            $fail('efatura::efatura.validation.number_bounds')->translate();
        }
    }

    private function withinBounds(BigDecimal $decimal): bool
    {
        return ($this->allowsNegative || ! $decimal->isNegative())
            && ($this->allowsZero || ! $decimal->isZero())
            && (! $this->maximum instanceof BigDecimal || ! $decimal->isGreaterThan($this->maximum));
    }
}
