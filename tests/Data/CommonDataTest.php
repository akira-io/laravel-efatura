<?php

declare(strict_types=1);

use Akira\Efatura\Data\AddressData;
use Akira\Efatura\Data\ContactsData;
use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Data\SoftwareData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\FiscalMoney;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
});

it('constructs and serializes complete immutable party details', function (): void {
    $party = PartyData::from([
        'taxId'    => ['value' => '123456789', 'countryCode' => 'CV'],
        'name'     => 'Example Company',
        'address'  => ['countryCode' => 'PT', 'addressDetail' => 'Rua Principal 12', 'buildingFloor' => '3'],
        'contacts' => ['email' => 'billing@example.com', 'mobilephone' => '2389912345'],
    ]);
    $party->validateEmitter();
    expect($party->taxId->value)->toBe('123456789')
        ->and($party->toArray()['address']['buildingFloor'])->toBe('3')
        ->and(fn (): string => $party->name = 'Changed')->toThrow(Error::class);
});

it('validates national and foreign tax identifiers without registry lookups', function (): void {
    expect((new TaxIdData('ABC-123', 'PT'))->countryCode)->toBe('PT');
    foreach (['012345678', '12345678', '1234567890', 'ABC123456'] as $value) {
        expect(fn (): TaxIdData => new TaxIdData($value, 'CV'))->toThrow(ValidationException::class);
    }

    expect(fn (): TaxIdData => new TaxIdData(str_repeat('A', 21), 'PT'))->toThrow(ValidationException::class)
        ->and(fn (): TaxIdData => new TaxIdData('AB CD', 'PT'))->toThrow(ValidationException::class)
        ->and(fn (): TaxIdData => new TaxIdData('12345', 'ZZ'))->toThrow(ValidationException::class);
});

it('keeps emitter contact requirements role specific', function (): void {
    $party = new PartyData(new TaxIdData('123456789', 'CV'), 'Example Company');
    expect(fn () => $party->validateEmitter())->toThrow(ValidationException::class);
    foreach ([new ContactsData(email: 'a@example.com'), new ContactsData(telephone: '1234567')] as $contacts) {
        expect(fn () => $contacts->validateEmitter())->toThrow(ValidationException::class);
    }

    new ContactsData(telephone: '1234567', email: 'a@example.com')->validateEmitter();
    expect(PartyData::from(['reference' => 'EP'])->reference->value)->toBe('EP');
    expect(fn (): PartyData => PartyData::from(['reference' => 'RP', 'name' => 'Example']))->toThrow(ValidationException::class);
});

it('enforces address and contact XSD boundaries', function (): void {
    expect(fn (): AddressData => new AddressData('CV', 'Praia'))->toThrow(ValidationException::class)
        ->and(fn (): AddressData => new AddressData('CV', 'Praia', addressCode: 'CV'))->toThrow(ValidationException::class)
        ->and(fn (): AddressData => new AddressData('PT', ' Double  spaces '))->toThrow(ValidationException::class)
        ->and(fn (): ContactsData => new ContactsData(telephone: '+2381234'))->toThrow(ValidationException::class)
        ->and(fn (): ContactsData => new ContactsData(email: 'invalid'))->toThrow(ValidationException::class)
        ->and(fn (): SoftwareData => new SoftwareData('lowercase', 'Example', '1'))->toThrow(ValidationException::class);
    expect((new SoftwareData('AB12', 'Example', '1.0'))->version)->toBe('1.0');
});

it('preserves decimal quantity precision and conditional standard units', function (): void {
    $quantity = QuantityData::from(['value' => '1.23456', 'unitCode' => 'C62', 'isStandardUnitCode' => true]);
    expect($quantity->toArray()['value'])->toBe('1.23456');
    expect((new QuantityData(BigDecimal::of('0'), 'custom'))->unitCode)->toBe('custom');
    expect(fn (): QuantityData => new QuantityData(BigDecimal::of('-1'), 'C62'))->toThrow(ValidationException::class)
        ->and(fn (): QuantityData => new QuantityData(BigDecimal::of('1.123456'), 'C62'))->toThrow(ValidationException::class)
        ->and(fn (): QuantityData => new QuantityData(BigDecimal::of('1'), 'custom', true))->toThrow(ValidationException::class);
});

it('requires exactly one tax representation and preserves optional tax total', function (): void {
    $tax = new TaxData(TaxType::ValueAddedTax, taxPercentage: BigDecimal::of('15.125'), taxTotal: FiscalMoney::exact('1.23456', 'CVE'));
    expect($tax->toArray()['taxTotal'])->toBe('1.23456');
    expect(fn (): TaxData => new TaxData(TaxType::NotApplicable))->toThrow(ValidationException::class)
        ->and(fn (): TaxData => new TaxData(TaxType::ValueAddedTax, taxPercentage: BigDecimal::of('15'), taxAmount: FiscalMoney::cve('1')))->toThrow(ValidationException::class)
        ->and(fn (): TaxData => new TaxData(TaxType::ValueAddedTax, taxPercentage: BigDecimal::of('15.1234')))->toThrow(ValidationException::class)
        ->and(fn (): TaxData => new TaxData(TaxType::StampTax, taxAmount: FiscalMoney::cve('1')))->toThrow(ValidationException::class);
});

it('rejects typed numeric bypasses through both Spatie entry points', function (): void {
    foreach (['from', 'validateAndCreate'] as $method) {
        expect(fn (): QuantityData => QuantityData::$method(['value' => BigDecimal::of('-1'), 'unitCode' => 'C62']))->toThrow(ValidationException::class)
            ->and(fn (): QuantityData => QuantityData::$method(['value' => null, 'unitCode' => 'C62']))->toThrow($method === 'from' ? TypeError::class : ValidationException::class)
            ->and(fn (): TaxData => TaxData::$method(['taxTypeCode' => TaxType::ValueAddedTax, 'taxAmount' => FiscalMoney::of('1', 'EUR')]))->toThrow(ValidationException::class);
    }
});

it('models discount amounts as money and percentages with five digits', function (): void {
    $discount = new DiscountData(BigDecimal::of('12.34567'));
    expect($discount->toArray()['value'])->toBe('12.34567');
    expect((new DiscountData(FiscalMoney::exact('1.23456', 'CVE'), DiscountValueType::Amount))->value)->toBeInstanceOf(Money::class);
    expect(fn (): DiscountData => new DiscountData(BigDecimal::of('100.00001')))->toThrow(ValidationException::class)
        ->and(fn (): DiscountData => new DiscountData(BigDecimal::of('1'), DiscountValueType::Amount))->toThrow(ValidationException::class);
});
