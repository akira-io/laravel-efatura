<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function validationErrorsOf(Closure $creation): array
{
    try {
        $creation();
    } catch (ValidationException $validationException) {
        return $validationException->errors();
    }

    test()->fail('Expected a ValidationException.');
}

it('reports a malformed top-level amount at its own field', function (mixed $amount, string $message): void {
    $errors = validationErrorsOf(fn (): TotalsData => TotalsData::from(F::totalsPayload(['payableAmount' => $amount])));

    expect($errors)->toBe(['payableAmount' => [$message]]);
})->with([
    'text'         => ['abc', 'Value must be a plain decimal number.'],
    'excess scale' => ['1.123456', 'Value exceeds the allowed decimal precision.'],
    'float'        => [1.5, 'Money must be an integer, decimal string, or Money value.'],
]);

it('reports a malformed nested decimal at its full path', function (): void {
    $lines                         = array_fill(0, 4, F::linePayload());
    $lines[3]['quantity']['value'] = 'abc';

    $errors = validationErrorsOf(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['lines' => $lines])));

    expect($errors)->toBe(['lines.3.quantity.value' => ['Value must be a plain decimal number.']]);
});

it('reports a cast failure in a nested amount list at its full path', function (): void {
    $alternatives    = array_fill(0, 4, ['value' => '1', 'currencyCode' => 'EUR', 'exchangeRate' => '110']);
    $alternatives[3] = ['value' => '1.123456', 'currencyCode' => 'EUR', 'exchangeRate' => '110'];

    $errors = validationErrorsOf(fn (): TotalsData => TotalsData::from(F::totalsPayload(['payableAlternativeAmounts' => $alternatives])));

    expect($errors)->toBe(['payableAlternativeAmounts.3.value' => ['Value exceeds the allowed decimal precision.']]);
});

it('reports an unknown alternative currency at the converted amount', function (): void {
    $errors = validationErrorsOf(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::from([
        'value' => '1', 'currencyCode' => 'IdR', 'exchangeRate' => '1',
    ]));

    expect($errors)->toBe(['value' => ['Currency must be a supported uppercase ISO code.']]);
});

it('reports a missing alternative currency at its own field', function (): void {
    $errors = validationErrorsOf(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::from([
        'value' => '1', 'exchangeRate' => '1',
    ]));

    expect($errors)->toBe(['currencyCode' => ['Currency must be a supported uppercase ISO code.']]);
});
