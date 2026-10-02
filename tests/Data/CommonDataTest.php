<?php

declare(strict_types=1);

use Akira\Efatura\Data\AddressData;
use Akira\Efatura\Data\ContactsData;
use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Data\SoftwareData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

it('constructs and serializes complete immutable party details', function (): void {
    $party = PartyData::from([
        'taxId'    => ['value' => '123456789', 'countryCode' => 'CV'],
        'name'     => 'Example Company',
        'address'  => ['countryCode' => 'CV', 'addressDetail' => 'Praia office', 'addressCode' => 'CV111111111011110101', 'buildingFloor' => '3'],
        'contacts' => ['email' => 'billing@example.com', 'mobilephone' => '2389912345'],
    ]);
    expect($party->taxId->value)->toBe('123456789')
        ->and($party->toArray()['address']['buildingFloor'])->toBe('3')
        ->and(fn (): string => $party->name = 'Changed')->toThrow(Error::class);
});

it('validates national and foreign tax identifiers without registry lookups', function (): void {
    expect((new TaxIdData('ABC-123', 'PT'))->countryCode)->toBe('PT');
    foreach (['012345678', '12345678', '1234567890', 'ABC123456'] as $value) {
        expect(fn (): TaxIdData => TaxIdData::from(['value' => $value, 'countryCode' => 'CV']))->toThrow(ValidationException::class);
    }

    expect(fn (): TaxIdData => TaxIdData::from(['value' => str_repeat('A', 21), 'countryCode' => 'PT']))->toThrow(ValidationException::class)
        ->and(fn (): TaxIdData => TaxIdData::from(['value' => 'AB CD', 'countryCode' => 'PT']))->toThrow(ValidationException::class)
        ->and(fn (): TaxIdData => TaxIdData::from(['value' => '12345', 'countryCode' => 'ZZ']))->toThrow(ValidationException::class);
});

it('keeps emitter contact requirements role specific', function (array $contacts, ?string $field): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    $emitter = [...F::payload()['emitter'], 'contacts' => $contacts];

    expect(PartyData::from($emitter)->contacts)->toBeInstanceOf(ContactsData::class);

    if ($field === null) {
        expect(ElectronicInvoiceData::from(F::payload(['emitter' => $emitter]))->emitter->contacts?->email)->toBe('a@example.com');
    } else {
        expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['emitter' => $emitter])))
            ->toThrow(function (ValidationException $exception) use ($field): void {
                expect($exception->errors())->toHaveKey($field);
            });
    }
})->with([
    'email only'      => [['email' => 'a@example.com'], 'emitter.contacts.telephone'],
    'telephone only'  => [['telephone' => '1234567'], 'emitter.contacts.email'],
    'email and phone' => [['telephone' => '1234567', 'email' => 'a@example.com'], null],
]);

it('requires emitter contacts but not receiver contacts', function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    $party = ['taxId' => ['value' => '123456789', 'countryCode' => 'CV'], 'name' => 'Example Company'];

    expect(ElectronicInvoiceData::from(F::payload(['receiver' => $party]))->receiver?->contacts)->toBeNull()
        ->and(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['emitter' => $party])))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toHaveKeys(['emitter.address', 'emitter.contacts']);
        });
});

it('distinguishes party references from identified parties', function (): void {
    expect(PartyData::from(['reference' => 'EP'])->reference->value)->toBe('EP');
    expect(fn (): PartyData => PartyData::from(['reference' => 'RP', 'name' => 'Example']))->toThrow(ValidationException::class);
});

it('enforces address and contact XSD boundaries', function (): void {
    expect(fn (): AddressData => AddressData::from(['countryCode' => 'CV', 'addressDetail' => 'Praia']))->toThrow(ValidationException::class)
        ->and(fn (): AddressData => AddressData::from(['countryCode' => 'CV', 'addressDetail' => 'Praia', 'addressCode' => 'CV']))->toThrow(ValidationException::class)
        ->and(fn (): AddressData => AddressData::from(['countryCode' => 'PT', 'addressDetail' => ' Double  spaces ']))->toThrow(ValidationException::class)
        ->and(fn (): ContactsData => ContactsData::from(['telephone' => '+2381234']))->toThrow(ValidationException::class)
        ->and(fn (): ContactsData => ContactsData::from(['email' => 'invalid']))->toThrow(ValidationException::class)
        ->and(fn (): SoftwareData => SoftwareData::from(['code' => 'lowercase', 'name' => 'Example', 'version' => '1']))->toThrow(ValidationException::class);
    expect((new SoftwareData('AB12', 'Example', '1.0'))->version)->toBe('1.0');
});

it('preserves decimal quantity precision and conditional standard units', function (): void {
    $quantity = QuantityData::from(['value' => '1.23456', 'unitCode' => 'C62', 'isStandardUnitCode' => true]);
    expect($quantity->toArray()['value'])->toBe('1.23456');
    expect((new QuantityData(BigDecimal::of('0'), 'custom'))->unitCode)->toBe('custom');
    expect(fn (): QuantityData => QuantityData::from(['value' => BigDecimal::of('-1'), 'unitCode' => 'C62']))->toThrow(ValidationException::class)
        ->and(fn (): QuantityData => QuantityData::from(['value' => BigDecimal::of('1.123456'), 'unitCode' => 'C62']))->toThrow(ValidationException::class)
        ->and(fn (): QuantityData => QuantityData::from(['value' => BigDecimal::of('1'), 'unitCode' => 'custom', 'isStandardUnitCode' => true]))->toThrow(ValidationException::class);
});

it('requires exactly one tax representation and preserves optional tax total', function (): void {
    $tax = new TaxData(TaxType::ValueAddedTax, taxPercentage: BigDecimal::of('15.125'), taxTotal: FiscalMoney::exact('1.23456', 'CVE'));
    expect($tax->toArray()['taxTotal'])->toBe('1.23456');
    expect(fn (): TaxData => TaxData::from(['taxTypeCode' => TaxType::NotApplicable]))->toThrow(ValidationException::class)
        ->and(fn (): TaxData => TaxData::from(['taxTypeCode' => TaxType::ValueAddedTax, 'taxPercentage' => BigDecimal::of('15'), 'taxAmount' => FiscalMoney::cve('1')]))->toThrow(ValidationException::class)
        ->and(fn (): TaxData => TaxData::from(['taxTypeCode' => TaxType::ValueAddedTax, 'taxPercentage' => BigDecimal::of('15.1234')]))->toThrow(ValidationException::class)
        ->and(fn (): TaxData => TaxData::from(['taxTypeCode' => TaxType::StampTax, 'taxAmount' => FiscalMoney::cve('1')]))->toThrow(ValidationException::class);
});

it('rejects typed numeric bypasses through both Spatie entry points', function (): void {
    foreach (['from', 'validateAndCreate'] as $method) {
        expect(fn (): QuantityData => QuantityData::$method(['value' => BigDecimal::of('-1'), 'unitCode' => 'C62']))->toThrow(ValidationException::class)
            ->and(fn (): QuantityData => QuantityData::$method(['value' => null, 'unitCode' => 'C62']))->toThrow(ValidationException::class)
            ->and(fn (): TaxData => TaxData::$method(['taxTypeCode' => TaxType::ValueAddedTax, 'taxAmount' => FiscalMoney::of('1', 'EUR')]))->toThrow(ValidationException::class);
    }
});

it('models discount amounts as money and percentages with five digits', function (): void {
    $discount = new DiscountData(BigDecimal::of('12.34567'));
    expect($discount->toArray()['value'])->toBe('12.34567');
    expect((new DiscountData(FiscalMoney::exact('1.23456', 'CVE'), DiscountValueType::Amount))->value)->toBeInstanceOf(Money::class);
    expect(fn (): DiscountData => DiscountData::from(['value' => BigDecimal::of('100.00001')]))->toThrow(ValidationException::class)
        ->and(fn (): DiscountData => DiscountData::from(['value' => BigDecimal::of('1'), 'valueType' => DiscountValueType::Amount]))->toThrow(ValidationException::class);
});
