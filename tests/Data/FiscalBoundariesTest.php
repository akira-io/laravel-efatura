<?php

declare(strict_types=1);

use Akira\Efatura\Data\AddressData;
use Akira\Efatura\Data\ContingencyData;
use Akira\Efatura\Data\DatePeriodData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Data\DurationData;
use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Data\FiscalDocumentData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Enums\StampTaxCode;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\FiscalValueFixtures;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Carbon\CarbonImmutable;

it('rejects pretyped invalid money in every owning field through construction', function (string $method, string $class, array $payload, string $field, Money $invalid, string $message): void {
    $input = [...$payload, $field => $invalid];

    expect(fn (): mixed => $class::$method($input))->toFailValidationOn($field, $message);
})->with([
    'from'              => ['from'],
    'validateAndCreate' => ['validateAndCreate'],
])->with(FiscalValueFixtures::moneyOwners())->with(FiscalValueFixtures::invalidMoney());

it('rejects wrong typed currency and bounds on discounts and alternate amounts', function (string $class, array $payload, string $field, string $message): void {
    expect(fn (): mixed => $class::from($payload))->toFailValidationOn($field, $message);
})->with(FiscalValueFixtures::invalidDiscountsAndAlternateAmounts());

it('accepts five decimal discounts through both Spatie entry points', function (string $method, array $payload, string $expected): void {
    expect(DiscountData::$method($payload)->toArray()['value'])->toBe($expected);
})->with([
    'from'              => ['from'],
    'validateAndCreate' => ['validateAndCreate'],
])->with([
    'amount'     => [['value' => '1.23456', 'valueType' => 'A'], '1.23456'],
    'percentage' => [['value' => '15.12345'], '15.12345'],
]);

it('accepts an exemption reason from the catalog', function (): void {
    expect((new TaxData(TaxType::NotApplicable, taxExemptionReasonCode: '1'))->taxExemptionReasonCode)->toBe('1');
});

it('accepts a typed stamp tax code with a minimal amount', function (): void {
    $tax = new TaxData(TaxType::StampTax, taxAmount: FiscalMoney::exact('0.00001', 'CVE'), stampTaxCode: StampTaxCode::Contracts);

    expect($tax->stampTaxCode)->toBe(StampTaxCode::Contracts);
});

it('casts a numeric stamp tax code to its enum', function (): void {
    expect(TaxData::validateAndCreate(['taxTypeCode' => 'IS', 'taxAmount' => '1', 'stampTaxCode' => 1])->stampTaxCode)->toBe(StampTaxCode::CreditOperations);
});

it('rejects tax percentages outside their bounds', function (string $percentage): void {
    $payload = ['taxTypeCode' => TaxType::ValueAddedTax, 'taxPercentage' => BigDecimal::of($percentage)];

    expect(fn (): TaxData => TaxData::from($payload))->toFailValidationOn('taxPercentage', 'The tax percentage is outside its permitted numeric bounds.');
})->with([
    'zero'              => ['0'],
    'negative'          => ['-1'],
    'above one hundred' => ['100.001'],
]);

it('rejects uncatalogued exemption and stamp tax codes', function (string $method, array $payload, string $field, string $message): void {
    expect(fn (): TaxData => TaxData::$method($payload))->toFailValidationOn($field, $message);
})->with(FiscalValueFixtures::uncataloguedTaxCodes());

it('rejects untyped values inside data lists', function (string $class, array $payload, string $field): void {
    expect(fn (): mixed => $class::from($payload))->toFailValidationOn($field, "The {$field} field must be an array.");
})->with([
    'item extra properties' => [ItemData::class, ['description' => 'Item', 'emitterIdentification' => 'SKU', 'extraProperties' => [new stdClass]], 'extraProperties.0'],
    'payments'              => [PaymentsData::class, ['payments' => [new stdClass]], 'payments.0'],
    'reference taxes'       => [ReferenceData::class, ['taxes' => [new stdClass]], 'taxes.0'],
]);

it('limits reference taxes to two entries', function (): void {
    $tax     = new TaxData(TaxType::ValueAddedTax, taxPercentage: BigDecimal::of('15'));
    $payload = ['taxes' => [$tax, $tax, $tax]];

    expect(fn (): ReferenceData => ReferenceData::from($payload))->toFailValidationOn('taxes', 'The taxes field must not have more than 2 items.');
});

it('limits line taxes to two entries', function (): void {
    $tax     = ['taxTypeCode' => 'IVA', 'taxPercentage' => '15'];
    $payload = F::linePayload(['taxes' => [$tax, $tax, $tax]]);

    expect(fn (): LineItemData => LineItemData::from($payload))->toFailValidationOn('taxes', 'The taxes field must not have more than 2 items.');
});

it('accepts a reference with a single tax', function (): void {
    $tax = new TaxData(TaxType::ValueAddedTax, taxPercentage: BigDecimal::of('15'));

    expect((new ReferenceData(taxes: [$tax]))->taxes)->toBe([$tax]);
});

it('accepts the official IUD shape with an optional old document flag', function (): void {
    $iud = 'CV1261002123456789' . str_repeat('0', 27);

    expect((new FiscalDocumentData($iud, false))->value)->toBe($iud)
        ->and((new FiscalDocumentData($iud))->isOldDocument)->toBeNull();
});

it('rejects the old document flag on an official IUD', function (): void {
    $payload = ['value' => 'CV1261002123456789' . str_repeat('0', 27), 'isOldDocument' => true];

    expect(fn (): FiscalDocumentData => FiscalDocumentData::from($payload))->toFailValidationOn('isOldDocument', 'The is old document field is prohibited.');
});

it('rejects a delivery date before the fiscal epoch', function (): void {
    $payload = ['deliveryDate' => CarbonImmutable::parse('2020-12-31'), 'address' => new AddressData('PT', 'Example address')];

    expect(fn (): DeliveryData => DeliveryData::from($payload))->toFailValidationOn('deliveryDate', 'The delivery date must use a valid fiscal date or time.');
});

it('accepts a single day date period', function (): void {
    expect(DatePeriodData::validateAndCreate(['startDate' => '2021-01-01', 'endDate' => '2021-01-01'])->toArray()['endDate'])->toBe('2021-01-01');
});

it('rejects invalid or reversed duration times', function (array $payload, string $field, string $message): void {
    expect(fn (): DurationData => DurationData::from($payload))->toFailValidationOn($field, $message);
})->with(FiscalValueFixtures::invalidDurations());

it('accepts the highest LED code on a contingency', function (): void {
    $contingency = new ContingencyData(CarbonImmutable::parse('2026-01-01'), ContingencyReason::Other, 99999, reasonDescription: 'Other issue description');

    expect($contingency->ledCode)->toBe(99999);
});

it('keeps extension content as text', function (): void {
    expect((new ExtraFieldData('CustomNote', '<child>text</child>', 'urn:example:custom'))->value)->toBe('<child>text</child>');
});

it('reserves official element names', function (string $name): void {
    $payload = ['name' => $name, 'value' => 'value'];

    expect(fn (): ExtraFieldData => ExtraFieldData::from($payload))->toFailValidationOn('name', 'The name is reserved for an official fiscal field.');
})->with([
    'rappel period'  => ['RappelPeriod'],
    'tax id'         => ['taxId'],
    'address detail' => ['AddressDetail'],
    'quantity'       => ['Quantity'],
    'self billing'   => ['SelfBilling'],
    'tax percentage' => ['TaxPercentage'],
]);

it('rejects the official namespace and malformed names on extension fields', function (array $payload, string $field, string $message): void {
    expect(fn (): ExtraFieldData => ExtraFieldData::from($payload))->toFailValidationOn($field, $message);
})->with([
    'official namespace' => [['name' => 'CustomNote', 'value' => 'value', 'namespace' => 'urn:cv:efatura:xsd:v1.0'], 'namespace', 'The selected namespace is invalid.'],
    'prefixed name'      => [['name' => 'invalid:name', 'value' => 'value'], 'name', 'The name field format is invalid.'],
]);

it('accepts a cataloged CV address', function (): void {
    expect(new AddressData('CV', 'Boca de Pedregal', addressCode: 'CV111111111011110101')->addressCode)->toBe('CV111111111011110101');
});

it('rejects coercion at cast boundaries', function (string $class, array $payload, string $field, string $message): void {
    expect(fn (): mixed => $class::from($payload))->toFailValidationOn($field, $message);
})->with(FiscalValueFixtures::castCoercions());

it('preserves fiscal calendar dates and wall clock times under host timezone settings', function (): void {
    config(['data.date_timezone' => 'Pacific/Honolulu']);
    $duration = new DurationData(CarbonImmutable::parse('2026-10-02', 'UTC'), CarbonImmutable::parse('09:00:00', 'UTC'));
    expect($duration->toArray()['startDate'])->toBe('2026-10-02')
        ->and($duration->toArray()['startTime'])->toBe('09:00:00');
});
