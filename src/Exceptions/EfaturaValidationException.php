<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

final class EfaturaValidationException extends EfaturaException
{
    private function __construct(string $errorCode, string $field, string $translationKey)
    {
        parent::__construct($errorCode, __('efatura::efatura.validation.' . $translationKey), $field);
    }

    public static function invalidDecimal(string $field): self
    {
        return new self('decimal.invalid', $field, 'invalid_decimal');
    }

    public static function decimalScaleExceeded(string $field): self
    {
        return new self('decimal.scale_exceeded', $field, 'decimal_scale_exceeded');
    }

    public static function invalidCurrency(string $field): self
    {
        return new self('money.invalid_currency', $field, 'invalid_currency');
    }

    public static function currencyMismatch(string $field): self
    {
        return new self('money.currency_mismatch', $field, 'currency_mismatch');
    }

    public static function invalidMoney(string $field): self
    {
        return new self('money.invalid', $field, 'invalid_money');
    }

    public function field(): string
    {
        return (string) $this->field;
    }
}
