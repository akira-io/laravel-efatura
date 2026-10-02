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
use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Data\PaymentData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Enums\StampTaxCode;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\FiscalMoney;
use Brick\Math\BigDecimal;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
});

it('rejects pretyped invalid money in every owning field through construction', function (string $class, array $payload, string $field): void {
    foreach (['from', 'validateAndCreate'] as $method) {
        foreach ([FiscalMoney::of('1', 'EUR'), Money::of('1.123456', 'CVE', new CustomContext(6))] as $invalid) {
            expect(fn () => $class::$method([...$payload, $field => $invalid]))->toThrow(ValidationException::class);
        }
    }
})->with([
    [TaxData::class, ['taxTypeCode' => 'IVA'], 'taxAmount'],
    [TaxData::class, ['taxTypeCode' => 'IVA', 'taxPercentage' => '15'], 'taxTotal'],
    [PaymentData::class, [], 'paymentAmount'],
    [ReferenceData::class, [], 'paymentAmount'],
    ...collect(['price', 'priceExtension', 'netTotal'])->map(fn (string $field): array => [
        LineItemData::class, ['quantity' => ['value' => '1', 'unitCode' => 'C62'], 'item' => ['description' => 'Item', 'emitterIdentification' => 'SKU']], $field,
    ])->all(),
    ...collect(['priceExtensionTotalAmount', 'netTotalAmount', 'taxTotalAmount', 'payableAmount', 'chargeTotalAmount', 'discountTotalAmount', 'withholdingTaxTotalAmount', 'payableRoundingAmount'])->map(fn (string $field): array => [
        TotalsData::class, ['priceExtensionTotalAmount' => '0', 'netTotalAmount' => '0', 'taxTotalAmount' => '0', 'payableAmount' => '0'], $field,
    ])->all(),
]);

it('rejects wrong typed currency and bounds on discounts and alternate amounts', function (): void {
    expect(fn (): DiscountData => new DiscountData(FiscalMoney::cve('1')))->toThrow(ValidationException::class)
        ->and(fn (): DiscountData => new DiscountData(FiscalMoney::of('1', 'EUR'), DiscountValueType::Amount))->toThrow(ValidationException::class)
        ->and(fn (): PayableAlternativeAmountData => new PayableAlternativeAmountData(FiscalMoney::of('1', 'EUR'), 'EUR', BigDecimal::of('0')))->toThrow(ValidationException::class)
        ->and(fn (): PayableAlternativeAmountData => new PayableAlternativeAmountData(FiscalMoney::of('1', 'EUR'), 'EUR', BigDecimal::of('1.123456')))->toThrow(ValidationException::class)
        ->and(fn (): PayableAlternativeAmountData => new PayableAlternativeAmountData(FiscalMoney::of('1', 'IDR'), 'IDR', BigDecimal::of('1')))->toThrow(ValidationException::class);
    foreach (['from', 'validateAndCreate'] as $method) {
        expect(DiscountData::$method(['value' => '1.23456', 'valueType' => 'A'])->toArray()['value'])->toBe('1.23456')
            ->and(DiscountData::$method(['value' => '15.12345'])->toArray()['value'])->toBe('15.12345');
    }
});

it('validates exemption catalog and tax boundaries independently of reconciliation', function (): void {
    expect((new TaxData(TaxType::NotApplicable, taxExemptionReasonCode: '1'))->taxExemptionReasonCode)->toBe('1');
    expect((new TaxData(TaxType::StampTax, taxAmount: FiscalMoney::exact('0.00001', 'CVE'), stampTaxCode: StampTaxCode::Contracts))->stampTaxCode)->toBe(StampTaxCode::Contracts);
    expect(TaxData::validateAndCreate(['taxTypeCode' => 'IS', 'taxAmount' => '1', 'stampTaxCode' => 1])->stampTaxCode)->toBe(StampTaxCode::CreditOperations);
    foreach (['0', '-1', '100.001'] as $percentage) {
        expect(fn (): TaxData => new TaxData(TaxType::ValueAddedTax, taxPercentage: BigDecimal::of($percentage)))->toThrow(ValidationException::class);
    }

    expect(fn (): TaxData => new TaxData(TaxType::NotApplicable, taxExemptionReasonCode: 'unknown'))->toThrow(ValidationException::class)
        ->and(fn (): TaxData => TaxData::validateAndCreate(['taxTypeCode' => 'IS', 'taxAmount' => '1', 'stampTaxCode' => 10]))->toThrow(ValidationException::class);
});

it('rejects mutable or untyped values inside constructor arrays', function (): void {
    expect(fn (): ItemData => new ItemData('Item', 'SKU', extraProperties: [new stdClass]))->toThrow(ValidationException::class)
        ->and(fn (): PaymentsData => new PaymentsData(payments: [new stdClass]))->toThrow(ValidationException::class)
        ->and(fn (): ReferenceData => new ReferenceData(taxes: [new stdClass]))->toThrow(ValidationException::class);
    $tax = new TaxData(TaxType::ValueAddedTax, taxPercentage: BigDecimal::of('15'));
    expect(fn (): ReferenceData => new ReferenceData(taxes: [$tax, $tax, $tax]))->toThrow(ValidationException::class);
    expect((new ReferenceData(taxes: [$tax]))->taxes)->toHaveCount(1);
});

it('accepts the official IUD shape and checks the optional old document flag', function (): void {
    $iud = 'CV1261002123456789' . str_repeat('0', 27);
    expect((new FiscalDocumentData($iud, false))->value)->toBe($iud)
        ->and((new FiscalDocumentData($iud))->isOldDocument)->toBeNull();
    expect(fn (): FiscalDocumentData => new FiscalDocumentData($iud, true))->toThrow(ValidationException::class);
});

it('validates direct immutable dates and exact time formats', function (): void {
    $address = new AddressData('PT', 'Example address');
    expect(fn (): DeliveryData => new DeliveryData(CarbonImmutable::parse('2020-12-31'), $address))->toThrow(ValidationException::class);
    expect(DatePeriodData::validateAndCreate(['startDate' => '2021-01-01', 'endDate' => '2021-01-01'])->toArray()['endDate'])->toBe('2021-01-01');
    expect(fn (): DurationData => DurationData::from(['startDate' => '2026-01-01', 'startTime' => '24:01:00']))->toThrow(ValidationException::class)
        ->and(fn (): DurationData => DurationData::from(['startDate' => '2026-01-01', 'startTime' => '09:00:00', 'endDate' => '2026-01-01', 'endTime' => '08:59:59']))->toThrow(ValidationException::class);
    expect((new ContingencyData(CarbonImmutable::parse('2026-01-01'), ContingencyReason::Other, 99999, reasonDescription: 'Other issue description'))->ledCode)->toBe(99999);
});

it('keeps extension content as text and reserves official element names', function (): void {
    expect((new ExtraFieldData('CustomNote', '<child>text</child>', 'urn:example:custom'))->value)->toBe('<child>text</child>');
    foreach (['RappelPeriod', 'taxId', 'AddressDetail', 'Quantity', 'SelfBilling', 'TaxPercentage'] as $name) {
        expect(fn (): ExtraFieldData => new ExtraFieldData($name, 'value'))->toThrow(ValidationException::class);
    }

    expect(fn (): ExtraFieldData => new ExtraFieldData('CustomNote', 'value', 'urn:cv:efatura:xsd:v1.0'))->toThrow(ValidationException::class)
        ->and(fn (): ExtraFieldData => new ExtraFieldData('invalid:name', 'value'))->toThrow(ValidationException::class);
});

it('accepts a cataloged CV address and rejects coercion at cast boundaries', function (): void {
    expect(new AddressData('CV', 'Boca de Pedregal', addressCode: 'CV111111111011110101')->addressCode)->toBe('CV111111111011110101');
    expect(fn (): DeliveryData => DeliveryData::from(['deliveryDate' => 123, 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Example address']]))->toThrow(ValidationException::class)
        ->and(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::from(['value' => '1', 'exchangeRate' => '1']))->toThrow(ValidationException::class);
});

it('preserves fiscal calendar dates and wall clock times under host timezone settings', function (): void {
    config(['data.date_timezone' => 'Pacific/Honolulu']);
    $duration = new DurationData(CarbonImmutable::parse('2026-10-02', 'UTC'), CarbonImmutable::parse('09:00:00', 'UTC'));
    expect($duration->toArray()['startDate'])->toBe('2026-10-02')
        ->and($duration->toArray()['startTime'])->toBe('09:00:00');
});
