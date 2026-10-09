<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Akira\Efatura\Enums\DecimalViolation;
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

        if ($maximum !== null && DecimalFormatter::violation($maximum, $scale) instanceof DecimalViolation) {
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
        $failure = $this->failure($value);

        if ($failure !== null) {
            $fail('efatura::efatura.validation.' . $failure)->translate();
        }
    }

    private function failure(mixed $value): ?string
    {
        if ($this->currency !== null) {
            return $this->moneyFailure($value);
        }

        return $this->decimalFailure($value);
    }

    private function moneyFailure(mixed $value): ?string
    {
        if ($value instanceof Money) {
            return $value->getCurrency()->getCurrencyCode() === $this->currency ? $this->decimalFailure($value->getAmount()) : 'currency_mismatch';
        }

        if ($value instanceof BigDecimal) {
            return 'invalid_money';
        }

        return \is_int($value) || \is_string($value) ? $this->decimalFailure($value) : 'invalid_money_input';
    }

    private function decimalFailure(mixed $value): ?string
    {
        if ($value instanceof Money) {
            return 'currency_mismatch';
        }

        $violation = DecimalFormatter::violation($value, $this->scale);

        if ($violation instanceof DecimalViolation) {
            return $violation->value;
        }

        return $this->withinBounds(DecimalFormatter::parse($value)) ? null : 'number_bounds';
    }

    private function withinBounds(BigDecimal $decimal): bool
    {
        return ($this->allowsNegative || ! $decimal->isNegative())
            && ($this->allowsZero || ! $decimal->isZero())
            && (! $this->maximum instanceof BigDecimal || ! $decimal->isGreaterThan($this->maximum));
    }
}
