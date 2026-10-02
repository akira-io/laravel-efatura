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
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('accepts the fixture inside the online window of the fiscal clock', function (): void {
    expect(ElectronicInvoiceData::from(F::payload()))->toBeInstanceOf(ElectronicInvoiceData::class);
});

it('rejects the fixture once the fiscal clock leaves the online window', function (): void {
    CarbonImmutable::setTestNow('2026-10-04T12:00:00-01:00');

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload()))->toThrow(ValidationException::class);
});

function expectFiscalGateField(Closure $callback, string $field): void
{
    try {
        $callback();
        test()->fail('Expected validation error for ' . $field);
    } catch (ValidationException $validationException) {
        expect(array_keys($validationException->errors()))->toContain($field);
    }
}

it('round trips absent and explicit null optional nested item data', function (): void {
    $item       = new ItemData('Item', 'SKU');
    $serialized = $item->toArray();

    foreach ([$serialized, ['description' => 'Item', 'emitterIdentification' => 'SKU']] as $payload) {
        $created = ItemData::validateAndCreate($payload);
        expect($created->packQuantity)->toBeNull()
            ->and($created->standardIdentification)->toBeNull();
    }

    expect(ItemData::from($serialized)->toArray())->toBe($serialized)
        ->and(ItemData::factory()->alwaysValidate()->from($serialized)->toArray())->toBe($serialized);
});

it('round trips nested item lists and exact numeric line values', function (): void {
    $minimal = LineItemData::validateAndCreate([
        'quantity' => ['value' => '1', 'unitCode' => 'C62'],
        'item'     => new ItemData('Item', 'SKU')->toArray(),
    ]);
    expect($minimal->item->packQuantity)->toBeNull();

    $item = new ItemData('Item', 'SKU', extraProperties: [new ExtraPropertyData('Colour', 'Blue')]);
    $line = LineItemData::validateAndCreate([
        'quantity'       => ['value' => '1.23456', 'unitCode' => 'C62'],
        'item'           => $item->toArray(),
        'price'          => '10.12345',
        'priceExtension' => '12.49875',
        'netTotal'       => '12.49875',
        'taxes'          => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15']],
    ]);

    $copy = LineItemData::validateAndCreate($line->toArray());
    expect($copy->quantity->value)->toBeInstanceOf(BigDecimal::class)
        ->and($copy->price)->toBeInstanceOf(Money::class)
        ->and($copy->item->extraProperties)->toHaveCount(1)
        ->and($copy->toArray())->toBe($line->toArray());
});

it('round trips a representative complete fiscal document', function (): void {
    $document = ElectronicInvoiceData::from(F::payload());
    expect(ElectronicInvoiceData::validateAndCreate($document->toArray())->toArray())->toBe($document->toArray());
});

it('requires an emitter address in direct and document validation', function (): void {
    $emitter = new PartyData(new TaxIdData('100200300', 'CV'), 'Emitter', contacts: new ContactsData(telephone: '1234567', email: 'emitter@example.cv'));
    expectFiscalGateField(fn () => $emitter->validateEmitter(), 'address');

    $payload = F::payload(['emitter' => [...F::payload()['emitter'], 'address' => null]]);
    foreach (['from', 'validateAndCreate'] as $method) {
        expectFiscalGateField(fn (): ElectronicInvoiceData => ElectronicInvoiceData::$method($payload), 'address');
    }

    $valid = ElectronicInvoiceData::from(F::payload());
    expectFiscalGateField(fn (): ElectronicInvoiceData => new ElectronicInvoiceData($valid->header, $emitter, $valid->receiver, $valid->lines, $valid->totals), 'address');
    expectFiscalGateField(fn () => Efatura::invoice()->emitter($emitter, 1)->receiver($valid->receiver)->line($valid->lines[0])->totals($valid->totals)->validate(), 'address');
});

it('requires a CV emitter address while keeping a foreign receiver address valid', function (): void {
    $foreign = new AddressData('PT', 'Rua Principal 12');
    $emitter = new PartyData(new TaxIdData('100200300', 'CV'), 'Emitter', $foreign, new ContactsData(telephone: '1234567', email: 'emitter@example.cv'));
    expectFiscalGateField(fn () => $emitter->validateEmitter(), 'address.countryCode');

    $receiver = PartyData::from(['taxId' => ['value' => 'ABC12345', 'countryCode' => 'PT'], 'name' => 'Receiver', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Rua Principal 12']]);
    expect($receiver->address->countryCode)->toBe('PT');
});
