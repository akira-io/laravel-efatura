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
use Akira\Efatura\Tests\Support\FiscalValueFixtures;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

it('constructs and serializes complete immutable party details', function (): void {
    $party = PartyData::from([
        'taxId'    => ['value' => '123456789', 'countryCode' => 'CV'],
        'name'     => 'Example Company',
        'address'  => ['countryCode' => 'CV', 'addressDetail' => 'Praia office', 'addressCode' => 'CV111111111011110101', 'buildingFloor' => '3'],
        'contacts' => ['email' => 'billing@example.com', 'mobilephone' => '2389912345'],
    ]);
    expect($party->taxId->value)->toBe('123456789')
        ->and($party->toArray()['address']['buildingFloor'])->toBe('3')
        ->and(fn (): string => $party->name = 'Changed')->toThrow(Error::class, 'Cannot modify readonly property ' . PartyData::class . '::$name');
});

it('accepts a foreign tax identifier without registry lookups', function (): void {
    expect((new TaxIdData('ABC-123', 'PT'))->countryCode)->toBe('PT');
});

it('rejects malformed tax identifiers', function (array $payload, string $field, string $message): void {
    expect(fn (): TaxIdData => TaxIdData::from($payload))->toFailValidationOn($field, $message);
})->with([
    'national leading zero'   => [['value' => '012345678', 'countryCode' => 'CV'], 'value', 'The value must be a valid tax identifier for its country.'],
    'national too short'      => [['value' => '12345678', 'countryCode' => 'CV'], 'value', 'The value must be a valid tax identifier for its country.'],
    'national too long'       => [['value' => '1234567890', 'countryCode' => 'CV'], 'value', 'The value must be a valid tax identifier for its country.'],
    'national with letters'   => [['value' => 'ABC123456', 'countryCode' => 'CV'], 'value', 'The value must be a valid tax identifier for its country.'],
    'foreign too long'        => [['value' => str_repeat('A', 21), 'countryCode' => 'PT'], 'value', 'The value must be a valid tax identifier for its country.'],
    'foreign with whitespace' => [['value' => 'AB CD', 'countryCode' => 'PT'], 'value', 'The value must be a valid tax identifier for its country.'],
    'uncatalogued country'    => [['value' => '12345', 'countryCode' => 'ZZ'], 'countryCode', 'The country code must be a code in the official catalog.'],
]);

it('accepts partial contacts on a standalone party', function (array $contacts): void {
    $party = [...F::payload()['emitter'], 'contacts' => $contacts];

    expect(PartyData::from($party)->contacts?->toArray())->toMatchArray($contacts);
})->with([
    'email only'      => [['email' => 'a@example.com']],
    'telephone only'  => [['telephone' => '1234567']],
    'email and phone' => [['telephone' => '1234567', 'email' => 'a@example.com']],
]);

it('accepts an emitter with both email and telephone', function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    $payload = F::payload(['emitter' => [...F::payload()['emitter'], 'contacts' => ['telephone' => '1234567', 'email' => 'a@example.com']]]);

    expect(ElectronicInvoiceData::from($payload)->emitter->contacts?->email)->toBe('a@example.com');
});

it('rejects an emitter missing one of its required contacts', function (array $contacts, string $field, string $message): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    $payload = F::payload(['emitter' => [...F::payload()['emitter'], 'contacts' => $contacts]]);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))->toFailValidationOn($field, $message);
})->with([
    'email only'     => [['email' => 'a@example.com'], 'emitter.contacts.telephone', 'The emitter.contacts.telephone field is required when emitter.contacts.mobilephone is not present.'],
    'telephone only' => [['telephone' => '1234567'], 'emitter.contacts.email', 'The emitter.contacts.email field is required.'],
]);

it('accepts a receiver without contacts', function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    $payload = F::payload(['receiver' => ['taxId' => ['value' => '123456789', 'countryCode' => 'CV'], 'name' => 'Example Company']]);

    expect(ElectronicInvoiceData::from($payload)->receiver?->contacts)->toBeNull();
});

it('requires emitter address and contacts', function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    $payload = F::payload(['emitter' => ['taxId' => ['value' => '123456789', 'countryCode' => 'CV'], 'name' => 'Example Company']]);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))
        ->toFailValidationOn('emitter.address', 'The emitter.address field is required.')
        ->toFailValidationOn('emitter.contacts', 'The emitter.contacts field is required.');
});

it('accepts a party reference', function (): void {
    expect(PartyData::from(['reference' => 'EP'])->reference->value)->toBe('EP');
});

it('rejects a party reference combined with identification', function (): void {
    $payload = ['reference' => 'RP', 'name' => 'Example'];

    expect(fn (): PartyData => PartyData::from($payload))
        ->toFailValidationOn('reference', 'The reference field prohibits tax id / name / address / contacts from being present.');
});

it('accepts software identification within XSD boundaries', function (): void {
    expect((new SoftwareData('AB12', 'Example', '1.0'))->version)->toBe('1.0');
});

it('requires an address code on every Cabo Verde address (Manual ADD-AC-R, p. 41)', function (): void {
    $payload = ['countryCode' => 'CV', 'addressDetail' => 'Praia'];

    expect(fn (): AddressData => AddressData::from($payload))
        ->toFailValidationOn('addressCode', 'The address code field is required when country code is CV.');
});

it('enforces address contact and software XSD boundaries', function (string $class, array $payload, string $field, string $message): void {
    expect(fn (): mixed => $class::from($payload))->toFailValidationOn($field, $message);
})->with([
    'CV address with short code'  => [AddressData::class, ['countryCode' => 'CV', 'addressDetail' => 'Praia', 'addressCode' => 'CV'], 'addressCode', 'The address code field format is invalid.'],
    'address detail with padding' => [AddressData::class, ['countryCode' => 'PT', 'addressDetail' => ' Double  spaces '], 'addressDetail', 'The address detail field format is invalid.'],
    'telephone with plus sign'    => [ContactsData::class, ['telephone' => '+2381234'], 'telephone', 'The telephone field format is invalid.'],
    'malformed email'             => [ContactsData::class, ['email' => 'invalid'], 'email', 'The email field format is invalid.'],
    'lowercase software code'     => [SoftwareData::class, ['code' => 'lowercase', 'name' => 'Example', 'version' => '1'], 'code', 'The code field format is invalid.'],
]);

it('preserves decimal quantity precision', function (): void {
    $quantity = QuantityData::from(['value' => '1.23456', 'unitCode' => 'C62', 'isStandardUnitCode' => true]);

    expect($quantity->toArray()['value'])->toBe('1.23456');
});

it('accepts a custom unit code when it is not declared standard', function (): void {
    expect((new QuantityData(BigDecimal::of('0'), 'custom'))->unitCode)->toBe('custom');
});

it('rejects quantities outside numeric and catalog boundaries', function (array $payload, string $field, string $message): void {
    expect(fn (): QuantityData => QuantityData::from($payload))->toFailValidationOn($field, $message);
})->with([
    'negative value'        => [['value' => BigDecimal::of('-1'), 'unitCode' => 'C62'], 'value', 'The value is outside its permitted numeric bounds.'],
    'six decimal places'    => [['value' => BigDecimal::of('1.123456'), 'unitCode' => 'C62'], 'value', 'Value exceeds the allowed decimal precision.'],
    'uncatalogued std unit' => [['value' => BigDecimal::of('1'), 'unitCode' => 'custom', 'isStandardUnitCode' => true], 'unitCode', 'The unit code must be a code in the official catalog.'],
]);

it('preserves the optional tax total exactly', function (): void {
    $tax = new TaxData(TaxType::ValueAddedTax, taxPercentage: BigDecimal::of('15.125'), taxTotal: FiscalMoney::exact('1.23456', 'CVE'));

    expect($tax->toArray()['taxTotal'])->toBe('1.23456');
});

it('requires exactly one tax representation', function (array $payload, string $field, string $message): void {
    expect(fn (): TaxData => TaxData::from($payload))->toFailValidationOn($field, $message);
})->with(FiscalValueFixtures::invalidTaxRepresentations());

it('rejects typed numeric bypasses through both Spatie entry points', function (string $method, string $class, array $payload, string $field, string $message): void {
    expect(fn (): mixed => $class::$method($payload))->toFailValidationOn($field, $message);
})->with([
    'from'              => ['from'],
    'validateAndCreate' => ['validateAndCreate'],
])->with(FiscalValueFixtures::numericBypasses());

it('models discount percentages with three decimal places', function (): void {
    expect(new DiscountData(BigDecimal::of('12.345'))->toArray()['value'])->toBe('12.345');
});

it('models discount amounts as exact money', function (): void {
    $discount = new DiscountData(FiscalMoney::exact('1.23456', 'CVE'), DiscountValueType::Amount);

    expect($discount->toArray()['value'])->toBe('1.23456')
        ->and($discount->toArray()['valueType'])->toBe(DiscountValueType::Amount->value);
});

it('rejects discounts outside their value type', function (array $payload, string $message): void {
    expect(fn (): DiscountData => DiscountData::from($payload))->toFailValidationOn('value', $message);
})->with([
    'percentage above one hundred' => [['value' => BigDecimal::of('100.001')], 'The value is outside its permitted numeric bounds.'],
    'percentage with four places'  => [['value' => '12.3456'], 'Value exceeds the allowed decimal precision.'],
    'amount given as a decimal'    => [['value' => BigDecimal::of('1'), 'valueType' => DiscountValueType::Amount], 'Money amount or currency is invalid.'],
]);
