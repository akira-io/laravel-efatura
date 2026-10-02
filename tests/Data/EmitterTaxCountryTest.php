<?php

declare(strict_types=1);

use Akira\Efatura\Data\ContactsData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

dataset('factories', ['from' => ['from'], 'validateAndCreate' => ['validateAndCreate']]);
dataset('foreign party', fn (): array => [[[
    'taxId'    => new TaxIdData('ABC12345', 'PT'),
    'name'     => 'Foreign Company',
    'contacts' => new ContactsData(telephone: '1234567', email: 'a@example.com'),
]]]);

it('accepts a foreign tax country on a standalone party', function (array $foreign, string $method): void {
    expect(PartyData::$method($foreign)->taxId->countryCode)->toBe('PT');
})->with('foreign party')->with('factories');

it('accepts a foreign tax country on the document receiver', function (array $foreign, string $method): void {
    $payload = F::payload(['receiver' => $foreign]);

    expect(ElectronicInvoiceData::$method($payload)->receiver?->taxId?->countryCode)->toBe('PT');
})->with('foreign party')->with('factories');

it('rejects a foreign tax country on the document emitter', function (array $foreign, string $method): void {
    $payload = F::payload(['emitter' => [...$foreign, 'address' => F::payload()['emitter']['address']]]);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::$method($payload))
        ->toFailValidationOn('emitter.taxId.countryCode', 'The selected emitter.tax id.country code is invalid.');
})->with('foreign party')->with('factories');
