<?php

declare(strict_types=1);

use Akira\Efatura\Data\AddressData;
use Akira\Efatura\Data\ContactsData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ExtraPropertyData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

dataset('factories', ['from' => ['from'], 'validateAndCreate' => ['validateAndCreate']]);
dataset('addressless emitter', fn (): array => [[
    new PartyData(new TaxIdData('100200300', 'CV'), 'Emitter', contacts: new ContactsData(telephone: '1234567', email: 'emitter@example.cv')),
]]);

it('accepts the fixture inside the online window of the fiscal clock', function (): void {
    $payload = F::payload();

    expect(ElectronicInvoiceData::from($payload)->header->issueDate->format('Y-m-d'))->toBe('2026-10-02');
});

it('rehydrates an issued document after the fiscal clock leaves the online window', function (): void {
    CarbonImmutable::setTestNow('2026-10-05T12:00:00-01:00');
    $payload = F::payload();

    expect(ElectronicInvoiceData::from($payload)->header->issueDate->format('Y-m-d'))->toBe('2026-10-02');
});

it('validates absent and explicit null optional nested item data as null', function (array $payload): void {
    $created = ItemData::validateAndCreate($payload);

    expect($created->packQuantity)->toBeNull()
        ->and($created->standardIdentification)->toBeNull();
})->with([
    'explicit nulls' => [fn (): array => new ItemData('Item', 'SKU')->toArray()],
    'absent keys'    => [['description' => 'Item', 'emitterIdentification' => 'SKU']],
]);

it('round trips serialized item data with explicit nulls', function (): void {
    $serialized = new ItemData('Item', 'SKU')->toArray();

    expect(ItemData::from($serialized)->toArray())->toBe($serialized)
        ->and(ItemData::factory()->alwaysValidate()->from($serialized)->toArray())->toBe($serialized);
});

it('validates a minimal line without an item pack quantity', function (): void {
    $payload = ['quantity' => ['value' => '1', 'unitCode' => 'C62'], 'item' => new ItemData('Item', 'SKU')->toArray()];

    expect(LineItemData::validateAndCreate($payload)->item->packQuantity)->toBeNull();
});

it('round trips nested item lists and exact numeric line values', function (): void {
    $item = new ItemData('Item', 'SKU', extraProperties: [new ExtraPropertyData('Colour', 'Blue')]);
    $line = LineItemData::validateAndCreate([
        'quantity'       => ['value' => '1.23456', 'unitCode' => 'C62'],
        'item'           => $item->toArray(),
        'price'          => '10.12345',
        'priceExtension' => '12.49875',
        'netTotal'       => '12.49875',
        'taxes'          => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15']],
    ]);

    $copy = LineItemData::validateAndCreate($line->toArray())->toArray();

    expect($copy)->toBe($line->toArray())
        ->and($copy['quantity']['value'])->toBe('1.23456')
        ->and($copy['price'])->toBe('10.12345')
        ->and($copy['item']['extraProperties'])->toBe([['name' => 'Colour', 'value' => 'Blue']]);
});

it('round trips a representative complete fiscal document', function (): void {
    $document = ElectronicInvoiceData::from(F::payload())->toArray();

    expect(ElectronicInvoiceData::validateAndCreate($document)->toArray())->toBe($document);
});

it('requires an emitter address in a document payload', function (string $method): void {
    $payload = F::payload(['emitter' => [...F::payload()['emitter'], 'address' => null]]);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::$method($payload))
        ->toFailValidationOn('emitter.address', 'The emitter.address field is required.');
})->with('factories');

it('requires an emitter address in a document built from data objects', function (PartyData $emitter): void {
    $valid   = ElectronicInvoiceData::from(F::payload());
    $payload = ['header' => $valid->header, 'emitter' => $emitter, 'receiver' => $valid->receiver, 'lines' => $valid->lines, 'totals' => $valid->totals];

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))
        ->toFailValidationOn('emitter.address', 'The emitter.address field is required.');
})->with('addressless emitter');

it('requires an emitter address in builder validation', function (PartyData $emitter): void {
    $valid   = ElectronicInvoiceData::from(F::payload());
    $builder = Efatura::invoice()->emitter($emitter, 1)->receiver($valid->receiver)->line($valid->lines[0])->totals($valid->totals);

    expect(fn (): ElectronicInvoiceData => $builder->build())
        ->toFailValidationOn('emitter.address', 'The emitter.address field is required.');
})->with('addressless emitter');

it('requires a CV emitter address', function (): void {
    $emitter = new PartyData(new TaxIdData('100200300', 'CV'), 'Emitter', new AddressData('PT', 'Rua Principal 12'), new ContactsData(telephone: '1234567', email: 'emitter@example.cv'));
    $payload = F::payload(['emitter' => $emitter]);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))
        ->toFailValidationOn('emitter.address.countryCode', 'The selected emitter.address.country code is invalid.');
});

it('keeps a foreign receiver address valid', function (): void {
    $payload = ['taxId' => ['value' => 'ABC12345', 'countryCode' => 'PT'], 'name' => 'Receiver', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Rua Principal 12']];

    expect(PartyData::from($payload)->address?->countryCode)->toBe('PT');
});
