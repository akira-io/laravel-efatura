<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\EfaturaValidationException;

it('exposes a stable code, the field and the translated message', function (EfaturaValidationException $exception, string $errorCode, string $message): void {
    expect($exception->errorCode)->toBe($errorCode)
        ->and($exception->field())->toBe('lines.0.price')
        ->and($exception->getMessage())->toBe($message);
})->with([
    'invalid decimal'   => [fn (): EfaturaValidationException => EfaturaValidationException::invalidDecimal('lines.0.price'), 'decimal.invalid', 'Value must be a plain decimal number.'],
    'scale exceeded'    => [fn (): EfaturaValidationException => EfaturaValidationException::decimalScaleExceeded('lines.0.price'), 'decimal.scale_exceeded', 'Value exceeds the allowed decimal precision.'],
    'invalid currency'  => [fn (): EfaturaValidationException => EfaturaValidationException::invalidCurrency('lines.0.price'), 'money.invalid_currency', 'Currency must be a supported uppercase ISO code.'],
    'currency mismatch' => [fn (): EfaturaValidationException => EfaturaValidationException::currencyMismatch('lines.0.price'), 'money.currency_mismatch', 'Money currency does not match the requested currency.'],
    'invalid money'     => [fn (): EfaturaValidationException => EfaturaValidationException::invalidMoney('lines.0.price'), 'money.invalid', 'Money amount or currency is invalid.'],
]);
