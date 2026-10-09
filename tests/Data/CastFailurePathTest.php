<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('reports a malformed top-level amount at its own field', function (mixed $amount, string $message): void {
    $payload = F::totalsPayload(['payableAmount' => $amount]);

    expect(fn (): TotalsData => TotalsData::from($payload))
        ->toThrow(function (ValidationException $exception) use ($message): void {
            expect($exception->errors())->toBe(['payableAmount' => [$message]]);
        });
})->with([
    'text'         => ['abc', 'Value must be a plain decimal number.'],
    'excess scale' => ['1.123456', 'Value exceeds the allowed decimal precision.'],
    'float'        => [1.5, 'Money must be an integer, decimal string, or Money value.'],
]);

it('reports a malformed nested decimal at its full path', function (): void {
    $lines                         = array_fill(0, 4, F::linePayload());
    $lines[3]['quantity']['value'] = 'abc';
    $payload                       = F::payload(['lines' => $lines]);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['lines.3.quantity.value' => ['Value must be a plain decimal number.']]);
        });
});

it('reports a cast failure in a nested amount list at its full path', function (): void {
    $alternatives    = array_fill(0, 4, ['value' => '1', 'currencyCode' => 'EUR', 'exchangeRate' => '110']);
    $alternatives[3] = ['value' => '1.123456', 'currencyCode' => 'EUR', 'exchangeRate' => '110'];
    $payload         = F::totalsPayload(['payableAlternativeAmounts' => $alternatives]);

    expect(fn (): TotalsData => TotalsData::from($payload))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['payableAlternativeAmounts.3.value' => ['Value exceeds the allowed decimal precision.']]);
        });
});

it('reports a missing alternative currency at its own field', function (): void {
    $payload = ['value' => '1', 'exchangeRate' => '1'];

    expect(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::from($payload))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['currencyCode' => ['Currency must be an uppercase code of the official currency catalog.']]);
        });
});
