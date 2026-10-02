<?php

declare(strict_types=1);

use Akira\Efatura\Data\AddressData;
use Akira\Efatura\Data\ContingencyData;
use Akira\Efatura\Data\DatePeriodData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\DurationData;
use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Data\ExtraPropertyData;
use Akira\Efatura\Data\FiscalDocumentData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Data\PayeeFinancialAccountData;
use Akira\Efatura\Data\PaymentData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\PaymentTermsData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\RentReceiptData;
use Akira\Efatura\Data\SelfBillingData;
use Akira\Efatura\Data\StandardIdentificationData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Data\TransportRouteData;
use Akira\Efatura\Money\FiscalMoney;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
});

it('models the full item and line graph with exact money and immutable arrays', function (): void {
    $line = LineItemData::from([
        'quantity' => ['value' => '2', 'unitCode' => 'C62'],
        'price'    => '1.23456', 'priceExtension' => '2.46912', 'netTotal' => '2.46912',
        'id'       => 'line-1', 'lineReferenceId' => 'source-1', 'orderLineReference' => 99999,
        'item'     => [
            'description'            => 'Example item', 'emitterIdentification' => 'SKU-1', 'name' => 'Product',
            'brandName'              => 'Brand', 'modelName' => 'Model', 'hazardousRiskIndicator' => false,
            'packQuantity'           => ['value' => '6', 'unitCode' => 'C62'],
            'standardIdentification' => ['gtin' => '123456789'],
            'extraProperties'        => [['name' => 'Colour', 'value' => 'Blue']],
        ],
    ]);
    expect($line->toArray()['price'])->toBe('1.23456')
        ->and($line->item->extraProperties[0])->toBeInstanceOf(ExtraPropertyData::class)
        ->and(fn (): ExtraPropertyData => $line->item->extraProperties[] = new ExtraPropertyData('Size', 'Large'))->toThrow(Error::class);
    expect(fn (): StandardIdentificationData => StandardIdentificationData::from([]))->toThrow(ValidationException::class)
        ->and(fn (): StandardIdentificationData => StandardIdentificationData::from(['gtin' => 'A', 'ean' => 'B']))->toThrow(ValidationException::class);
});

it('models every total independently without premature reconciliation', function (): void {
    $totals = TotalsData::from([
        'priceExtensionTotalAmount' => '1.12345', 'netTotalAmount' => '1.12345',
        'taxTotalAmount'            => '0', 'payableAmount' => '1.12', 'payableRoundingAmount' => '-0.00345',
        'withholdingTaxTotalAmount' => '0.1', 'chargeTotalAmount' => '0', 'discountTotalAmount' => '0',
        'payableAlternativeAmounts' => [['value' => '1.23456', 'currencyCode' => 'EUR', 'exchangeRate' => '110.265']],
    ]);
    expect($totals->toArray()['payableRoundingAmount'])->toBe('-0.00345')
        ->and($totals->payableAlternativeAmounts[0]->value->getCurrency()->getCurrencyCode())->toBe('EUR');
    expect(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::from(['value' => '1', 'currencyCode' => 'IdR', 'exchangeRate' => '1']))->toThrow(ValidationException::class)
        ->and(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::from(['value' => FiscalMoney::of('1', 'USD'), 'currencyCode' => 'EUR', 'exchangeRate' => BigDecimal::of('1')]))->toThrow(ValidationException::class);
});

it('models bank account choices and separates invoice terms from payments', function (): void {
    $account = new PayeeFinancialAccountData('Account Holder', nib: '123456789012345678901');
    $terms   = new PaymentTermsData('Payment within thirty days');
    $invoice = new PaymentsData(paymentTerms: $terms, payeeFinancialAccounts: [$account]);
    $actual  = PaymentsData::from(['payments' => [['paymentMeansCode' => '10', 'paymentReference' => 'R1', 'paymentDate' => '2026-10-02', 'paymentAmount' => '1.23456']]]);
    expect($invoice->paymentTerms->note)->toBe('Payment within thirty days')
        ->and($actual->toArray()['payments'][0]['paymentDate'])->toBe('2026-10-02');
    expect(fn (): PayeeFinancialAccountData => PayeeFinancialAccountData::from(['name' => 'Account Holder']))->toThrow(ValidationException::class)
        ->and(fn (): PayeeFinancialAccountData => PayeeFinancialAccountData::from(['name' => 'Account Holder', 'accountNumber' => '1234', 'nib' => '123456789012345678901']))->toThrow(ValidationException::class)
        ->and(fn (): PaymentsData => PaymentsData::from(['paymentTerms' => $terms, 'payments' => [new PaymentData]]))->toThrow(ValidationException::class)
        ->and(fn (): PaymentData => PaymentData::from(['paymentAmount' => FiscalMoney::cve('0')]))->toThrow(ValidationException::class);
});

it('keeps fiscal reference format and old document indication consistent', function (): void {
    $old       = new FiscalDocumentData('1/2020/ABC/123', true);
    $reference = new ReferenceData(fiscalDocument: $old);
    expect($reference->fiscalDocument->value)->toBe('1/2020/ABC/123');
    expect(fn (): FiscalDocumentData => FiscalDocumentData::from(['value' => '1/2020/ABC/123', 'isOldDocument' => false]))->toThrow(ValidationException::class)
        ->and(fn (): ReferenceData => ReferenceData::from(['innerDocumentNumber' => 'internal']))->toThrow(ValidationException::class);
});

it('validates strict immutable dates and paired chronological transport duration', function (): void {
    $duration = DurationData::from(['startDate' => '2026-10-02', 'startTime' => '09:00:00', 'endDate' => '2026-10-02', 'endTime' => '10:00:00']);
    $address  = new AddressData('PT', 'Example address');
    $route    = TransportRouteData::from(['locations' => [
        ['address' => $address, 'duration' => $duration, 'transportModeCode' => '3', 'vehicleRegistrationCode' => 'AB12'],
        ['address' => $address, 'duration' => $duration, 'transportModeCode' => '0'],
    ]]);
    expect($route->locations[0]->duration->startDate)->toBeInstanceOf(CarbonImmutable::class)
        ->and($duration->toArray()['startTime'])->toBe('09:00:00')
        ->and(new DeliveryData(CarbonImmutable::parse('2026-10-02'), $address)->toArray()['deliveryDate'])->toBe('2026-10-02');
    expect(fn (): DatePeriodData => DatePeriodData::from(['startDate' => '2026-02-30', 'endDate' => '2026-03-01']))->toThrow(ValidationException::class)
        ->and(fn (): DatePeriodData => DatePeriodData::from(['startDate' => '2020-12-31', 'endDate' => '2026-03-01']))->toThrow(ValidationException::class)
        ->and(fn (): DatePeriodData => DatePeriodData::from(['startDate' => '2026-03-02', 'endDate' => '2026-03-01']))->toThrow(ValidationException::class)
        ->and(fn (): DurationData => DurationData::from(['startDate' => '2026-10-02', 'startTime' => '09:00:00', 'endDate' => '2026-10-03']))->toThrow(ValidationException::class)
        ->and(fn (): TransportRouteData => TransportRouteData::from(['locations' => []]))->toThrow(ValidationException::class);
});

it('models rent self billing contingency and custom values explicitly', function (): void {
    $rent        = RentReceiptData::from(['assetId' => 'HOUSE-1', 'rentPurposeTypeCode' => '2', 'contractTypeCode' => '1', 'rentTypeCode' => '1', 'referencePeriod' => '2026-10', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Example address']]);
    $billing     = new SelfBillingData('12345678-1234-1234-1234-123456789012', '1234');
    $contingency = ContingencyData::from(['issueDate' => '2026-10-02', 'issueTime' => '09:00:00', 'reasonTypeCode' => '1', 'ledCode' => 1]);
    expect($rent->referencePeriod)->toBe('2026-10')
        ->and($billing->authorizationCode)->toBe('1234')
        ->and($contingency->toArray()['issueTime'])->toBe('09:00:00')
        ->and((new ExtraFieldData('CustomerTag', 'value'))->value)->toBe('value');
    expect(fn (): ContingencyData => ContingencyData::from(['issueDate' => '2026-10-02', 'reasonTypeCode' => '0', 'ledCode' => 1]))->toThrow(ValidationException::class)
        ->and(fn (): ExtraFieldData => ExtraFieldData::from(['name' => 'PayableAmount', 'value' => '1']))->toThrow(ValidationException::class)
        ->and(fn (): SelfBillingData => SelfBillingData::from(['authorizationId' => 'invalid', 'authorizationCode' => '1234']))->toThrow(ValidationException::class);
});
