<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

it('reports each fiscal number failure with its own message', function (mixed $value, FiscalNumber $rule, ?string $message): void {
    $errors = Validator::make(['value' => $value], ['value' => [$rule]])->errors()->get('value');

    expect($errors)->toBe($message === null ? [] : [$message]);
})->with([
    'plain decimal'          => ['1.5', FiscalNumber::nonNegative(), null],
    'text'                   => ['abc', FiscalNumber::nonNegative(), 'Value must be a plain decimal number.'],
    'float'                  => [1.5, FiscalNumber::nonNegative(), 'Value must be a plain decimal number.'],
    'scientific notation'    => ['1e3', FiscalNumber::nonNegative(), 'Value must be a plain decimal number.'],
    'scale exceeded'         => ['1.123456', FiscalNumber::nonNegative(), 'Value exceeds the allowed decimal precision.'],
    'custom scale exceeded'  => ['15.1234', FiscalNumber::positive(3), 'Value exceeds the allowed decimal precision.'],
    'insignificant zeros'    => ['15.12300', FiscalNumber::positive(3), null],
    'zero when positive'     => ['0', FiscalNumber::positive(), 'The value is outside its permitted numeric bounds.'],
    'zero when non-negative' => ['0', FiscalNumber::nonNegative(), null],
    'negative'               => ['-1', FiscalNumber::nonNegative(), 'The value is outside its permitted numeric bounds.'],
    'above maximum'          => ['100.001', FiscalNumber::positive(3, '100'), 'The value is outside its permitted numeric bounds.'],
    'at maximum'             => ['100', FiscalNumber::positive(3, '100'), null],
    'signed amount'          => [Money::of('-0.5', 'CVE'), FiscalNumber::signedAmount('CVE'), null],
    'negative amount'        => [Money::of('-0.5', 'CVE'), FiscalNumber::amount('CVE'), 'The value is outside its permitted numeric bounds.'],
    'zero positive amount'   => [Money::of('0', 'CVE'), FiscalNumber::positiveAmount('CVE'), 'The value is outside its permitted numeric bounds.'],
    'currency mismatch'      => [Money::of('1', 'USD'), FiscalNumber::amount('CVE'), 'Money currency does not match the requested currency.'],
    'money without currency' => [Money::of('1', 'CVE'), FiscalNumber::nonNegative(), 'Money currency does not match the requested currency.'],
    'decimal for an amount'  => [BigDecimal::of('1'), FiscalNumber::amount('CVE'), 'Money amount or currency is invalid.'],
]);

it('rejects an impossible rule definition as a programming error', function (Closure $definition): void {
    expect($definition)->toThrow(DefinitionException::class);
})->with([
    'malformed maximum'    => [fn (): FiscalNumber => FiscalNumber::positive(3, 'one hundred')],
    'maximum beyond scale' => [fn (): FiscalNumber => FiscalNumber::nonNegative(2, '100.001')],
    'negative scale'       => [fn (): FiscalNumber => FiscalNumber::positive(-1)],
]);

it('reports an excess line amount precision at its full path', function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    $lines             = array_fill(0, 4, F::linePayload());
    $lines[3]['price'] = '1.123456';

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['lines' => $lines])))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['lines.3.price' => ['Value exceeds the allowed decimal precision.']]);
        });

    CarbonImmutable::setTestNow();
});
