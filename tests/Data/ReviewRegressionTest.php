<?php

declare(strict_types=1);

use Akira\Efatura\Data\AddressData;
use Akira\Efatura\Data\ContactsData;
use Akira\Efatura\Data\DatePeriodData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Data\ExtraPropertyData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\PayeeFinancialAccountData;
use Akira\Efatura\Data\PaymentData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Data\StandardIdentificationData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Enums\PartyReference;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

it('preserves explicitly permitted empty extension text', function (string $value): void {
    expect(new ExtraFieldData('CustomFlag', $value)->value)->toBe($value);
    foreach (['from', 'validateAndCreate'] as $method) {
        expect(ExtraFieldData::$method(['name' => 'CustomFlag', 'value' => $value])->toArray()['value'])->toBe($value);
    }
})->with(['', '   ', "\t\n"]);

it('still requires valid extension names when their text is empty', function (): void {
    foreach (['', '   ', "\t\n"] as $name) {
        foreach (['from', 'validateAndCreate'] as $method) {
            expect(fn (): ExtraFieldData => ExtraFieldData::$method(['name' => $name, 'value' => '']))->toThrow(ValidationException::class);
        }
    }
});

it('requires nonempty clean text for item properties', function (): void {
    foreach (['', '   ', "\t\n"] as $value) {
        foreach (['from', 'validateAndCreate'] as $method) {
            expect(fn (): ExtraPropertyData => ExtraPropertyData::$method(['name' => 'CustomFlag', 'value' => $value]))->toThrow(ValidationException::class);
        }
    }
});

it('rejects supplied empty optional strings without discarding them', function (string $class, array $payload, string $field): void {
    foreach (['', '   ', "\t\n"] as $empty) {
        $input = [...$payload, $field => $empty];
        foreach (['from', 'validateAndCreate'] as $method) {
            expect(fn () => $class::$method($input))->toThrow(ValidationException::class);
        }
    }

    expect(new $class(...[...$payload, $field => null])->{$field})->toBeNull();
    foreach (['from', 'validateAndCreate'] as $method) {
        expect($class::$method([...$payload, $field => null])->{$field})->toBeNull()
            ->and($class::$method($payload)->{$field})->toBeNull();
    }
})->with([
    ...collect(['telephone', 'mobilephone', 'telefax', 'email', 'website'])->map(fn (string $field): array => [ContactsData::class, [], $field])->all(),
    ...collect(['ean', 'upc', 'pharmacode'])->map(fn (string $field): array => [StandardIdentificationData::class, ['gtin' => 'ABC'], $field])->all(),
    [AddressData::class, ['countryCode' => 'PT', 'addressDetail' => 'Example address'], 'street'],
    [ItemData::class, ['description' => 'Item', 'emitterIdentification' => 'SKU'], 'name'],
    [PaymentData::class, [], 'paymentReference'],
    [ExtraFieldData::class, ['name' => 'CustomNote', 'value' => 'Text'], 'namespace'],
    [PayeeFinancialAccountData::class, ['name' => 'Bank', 'accountNumber' => '12345'], 'nib'],
    [PartyData::class, ['reference' => PartyReference::Receiver], 'name'],
]);

it('restricts emitter tax country while permitting foreign parties in other roles', function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    $foreign = ['taxId' => new TaxIdData('ABC12345', 'PT'), 'name' => 'Foreign Company', 'contacts' => new ContactsData(telephone: '1234567', email: 'a@example.com')];
    $emitter = [...$foreign, 'address' => ['countryCode' => 'CV', 'addressDetail' => 'Praia office', 'addressCode' => 'CV111111111011110101']];
    foreach (['from', 'validateAndCreate'] as $method) {
        expect(PartyData::$method($foreign)->taxId->countryCode)->toBe('PT')
            ->and(ElectronicInvoiceData::$method(F::payload(['receiver' => $foreign]))->receiver?->taxId?->countryCode)->toBe('PT');

        try {
            ElectronicInvoiceData::$method(F::payload(['emitter' => $emitter]));
            test()->fail('Expected a ValidationException.');
        } catch (ValidationException $validationException) {
            expect($validationException->errors())->toHaveKey('emitter.taxId.countryCode');
        }
    }
});

it('compares fiscal periods as calendar dates regardless of hidden time and timezone', function (): void {
    $payload = ['startDate' => CarbonImmutable::parse('2026-10-02 23:00:00', 'Pacific/Honolulu'), 'endDate' => CarbonImmutable::parse('2026-10-02 00:00:00', 'Pacific/Kiritimati')];
    expect(new DatePeriodData(...$payload)->toArray()['endDate'])->toBe('2026-10-02');
    foreach (['from', 'validateAndCreate'] as $method) {
        expect(DatePeriodData::$method($payload)->toArray()['endDate'])->toBe('2026-10-02');
        expect(fn (): DatePeriodData => DatePeriodData::$method(['startDate' => '2026-10-03', 'endDate' => '2026-10-02']))->toThrow(ValidationException::class);
    }
});

it('requires positive quantities in line and item pack fields', function (): void {
    $quantity = new QuantityData(BigDecimal::of('0'), 'C62');
    foreach (['from', 'validateAndCreate'] as $method) {
        foreach ([$quantity, ['value' => '0', 'unitCode' => 'C62']] as $input) {
            expect(fn (): LineItemData => LineItemData::$method(['quantity' => $input, 'item' => ['description' => 'Item', 'emitterIdentification' => 'SKU']]))->toThrow(ValidationException::class)
                ->and(fn (): ItemData => ItemData::$method(['description' => 'Item', 'emitterIdentification' => 'SKU', 'packQuantity' => $input]))->toThrow(ValidationException::class);
        }
    }

    $positive = new QuantityData(BigDecimal::of('0.00001'), 'C62');
    expect(new LineItemData($positive, new ItemData('Item', 'SKU', packQuantity: $positive))->quantity->value->__toString())->toBe('0.00001');
});

it('requires a charge line reference without checking cross line existence', function (): void {
    $quantity = new QuantityData(BigDecimal::of('1'), 'C62');
    $item     = new ItemData('Item', 'SKU');
    $payload  = ['quantity' => $quantity, 'item' => $item, 'lineTypeCode' => LineType::Charge];
    foreach (['from', 'validateAndCreate'] as $method) {
        $input = [...$payload, 'quantity' => $quantity->toArray(), 'item' => ['description' => 'Item', 'emitterIdentification' => 'SKU']];
        expect(fn (): LineItemData => LineItemData::$method($input))->toThrow(ValidationException::class);
        expect(LineItemData::$method([...$input, 'lineReferenceId' => 'L1'])->lineReferenceId)->toBe('L1');
    }

    expect(new LineItemData($quantity, $item, LineType::Charge, lineReferenceId: 'L1')->lineReferenceId)->toBe('L1');
});
