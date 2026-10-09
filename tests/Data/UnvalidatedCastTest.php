<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Fixtures\UnvalidatedFiscalDateData;
use Akira\Efatura\Tests\Fixtures\UnvalidatedForeignAmountData;

it('rejects a fiscal date before the earliest accepted date without a validation rule', function (): void {
    $payload = ['date' => '2020-12-31'];

    expect(fn (): UnvalidatedFiscalDateData => UnvalidatedFiscalDateData::from($payload))
        ->toFailValidationOn('date', 'The date must use a valid fiscal date or time.');
});

it('rejects text the fiscal date format cannot parse', function (): void {
    $payload = ['date' => 'not a date'];

    expect(fn (): UnvalidatedFiscalDateData => UnvalidatedFiscalDateData::from($payload))
        ->toFailValidationOn('date', 'The date must use a valid fiscal date or time.');
});

it('formats a native date with the fiscal date format', function (): void {
    $data = new UnvalidatedFiscalDateData(new DateTimeImmutable('2026-10-02 23:30:00', new DateTimeZone('Atlantic/Cape_Verde')));

    expect($data->toArray())->toBe(['date' => '2026-10-02']);
});

it('rejects a foreign amount without a currency code', function (): void {
    $payload = ['value' => '1'];

    expect(fn (): UnvalidatedForeignAmountData => UnvalidatedForeignAmountData::from($payload))
        ->toFailValidationOn('currencyCode', 'Currency must be an uppercase code of the official currency catalog.');
});
