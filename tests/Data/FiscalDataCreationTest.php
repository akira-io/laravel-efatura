<?php

declare(strict_types=1);

use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Tests\Fixtures\PlainQuantityData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;

it('validates fiscal data created through its default factory', function (): void {
    $payload = F::totalsPayload(['payableAmount' => '-1']);

    expect(fn (): TotalsData => TotalsData::factory()->from($payload))
        ->toFailValidationOn('payableAmount', 'The payable amount is outside its permitted numeric bounds.');
});

it('honours a creation context supplied to the factory', function (): void {
    $payload = F::totalsPayload(['payableAmount' => '-1']);
    $context = TotalsData::factory()->withoutValidation()->get();

    expect((string) TotalsData::factory($context)->from($payload)->payableAmount->getAmount())->toBe('-1.00000');
});

it('accepts plain Spatie data nested in a fiscal payload by its array form', function (): void {
    $payload = F::linePayload(['quantity' => new PlainQuantityData('2', 'C62')]);

    expect(LineItemData::from($payload)->quantity->toArray())->toBe(['value' => '2', 'unitCode' => 'C62', 'isStandardUnitCode' => false]);
});
