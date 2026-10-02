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
use Akira\Efatura\Tests\Support\FiscalValueFixtures;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

it('models the full item and line graph with exact money and immutable arrays', function (): void {
    $line = LineItemData::from(FiscalValueFixtures::completeLineItem());

    expect($line->toArray()['price'])->toBe('1.23456')
        ->and($line->item->extraProperties[0]->name)->toBe('Colour')
        ->and($line->item->extraProperties[0]->value)->toBe('Blue')
        ->and(fn (): ExtraPropertyData => $line->item->extraProperties[] = new ExtraPropertyData('Size', 'Large'))->toThrow(Error::class);
});

it('requires exactly one standard identification', function (array $payload, string $field, string $message): void {
    expect(fn (): StandardIdentificationData => StandardIdentificationData::from($payload))->toFailValidationOn($field, $message);
})->with([
    'none given' => [[], 'gtin', 'The gtin field is required when none of ean / upc / pharmacode are present.'],
    'two given'  => [['gtin' => 'A', 'ean' => 'B'], 'gtin', 'The gtin field prohibits ean / upc / pharmacode from being present.'],
]);

it('models every total independently without premature reconciliation', function (): void {
    $totals = TotalsData::from([
        'priceExtensionTotalAmount' => '1.12345', 'netTotalAmount' => '1.12345',
        'taxTotalAmount'            => '0', 'payableAmount' => '1.12', 'payableRoundingAmount' => '-0.00345',
        'withholdingTaxTotalAmount' => '0.1', 'chargeTotalAmount' => '0', 'discountTotalAmount' => '0',
        'payableAlternativeAmounts' => [['value' => '1.23456', 'currencyCode' => 'EUR', 'exchangeRate' => '110.265']],
    ]);
    expect($totals->toArray()['payableRoundingAmount'])->toBe('-0.00345')
        ->and($totals->payableAlternativeAmounts[0]->value->getCurrency()->getCurrencyCode())->toBe('EUR');
});

it('keeps the declared alternate currency code as given', function (): void {
    $amount = PayableAlternativeAmountData::from(['value' => '1', 'currencyCode' => 'IdR', 'exchangeRate' => '1']);

    expect($amount->value->getCurrency()->getCurrencyCode())->toBe('IdR');
});

it('rejects an alternate amount in a currency other than its declared one', function (): void {
    $payload = ['value' => FiscalMoney::of('1', 'USD'), 'currencyCode' => 'EUR', 'exchangeRate' => BigDecimal::of('1')];

    expect(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::from($payload))
        ->toFailValidationOn('value', 'Money currency does not match the requested currency.');
});

it('models bank account choices and separates invoice terms from payments', function (): void {
    $account = new PayeeFinancialAccountData('Account Holder', nib: '123456789012345678901');
    $invoice = new PaymentsData(paymentTerms: new PaymentTermsData('Payment within thirty days'), payeeFinancialAccounts: [$account]);
    $actual  = PaymentsData::from(['payments' => [['paymentMeansCode' => '10', 'paymentReference' => 'R1', 'paymentDate' => '2026-10-02', 'paymentAmount' => '1.23456']]]);
    expect($invoice->paymentTerms->note)->toBe('Payment within thirty days')
        ->and($invoice->payeeFinancialAccounts[0]->nib)->toBe('123456789012345678901')
        ->and($actual->toArray()['payments'][0]['paymentDate'])->toBe('2026-10-02');
});

it('requires exactly one bank account identifier', function (array $payload, string $field, string $message): void {
    expect(fn (): PayeeFinancialAccountData => PayeeFinancialAccountData::from($payload))->toFailValidationOn($field, $message);
})->with([
    'neither identifier' => [['name' => 'Account Holder'], 'accountNumber', 'The account number field is required when none of nib are present.'],
    'both identifiers'   => [['name' => 'Account Holder', 'accountNumber' => '1234', 'nib' => '123456789012345678901'], 'accountNumber', 'The account number field prohibits nib from being present.'],
]);

it('rejects actual payments alongside invoice payment terms', function (): void {
    $payload = ['paymentTerms' => new PaymentTermsData('Payment within thirty days'), 'payments' => [new PaymentData]];

    expect(fn (): PaymentsData => PaymentsData::from($payload))
        ->toFailValidationOn('payments', 'The payments field prohibits payment due date / payment terms / payee financial accounts from being present.');
});

it('rejects a zero payment amount', function (): void {
    $payload = ['paymentAmount' => FiscalMoney::cve('0')];

    expect(fn (): PaymentData => PaymentData::from($payload))->toFailValidationOn('paymentAmount', 'The payment amount is outside its permitted numeric bounds.');
});

it('references an old format fiscal document flagged as old', function (): void {
    $reference = new ReferenceData(fiscalDocument: new FiscalDocumentData('1/2020/ABC/123', true));

    expect($reference->fiscalDocument->value)->toBe('1/2020/ABC/123')
        ->and($reference->fiscalDocument->isOldDocument)->toBeTrue();
});

it('rejects an old format fiscal document not flagged as old', function (): void {
    $payload = ['value' => '1/2020/ABC/123', 'isOldDocument' => false];

    expect(fn (): FiscalDocumentData => FiscalDocumentData::from($payload))->toFailValidationOn('isOldDocument', 'The is old document field is prohibited.');
});

it('requires a fiscal document on a reference carrying only an inner number', function (): void {
    $payload = ['innerDocumentNumber' => 'internal'];

    expect(fn (): ReferenceData => ReferenceData::from($payload))
        ->toFailValidationOn('fiscalDocument', 'The fiscal document field is required when none of payment amount / taxes are present.');
});

it('models strict immutable dates on a paired transport route', function (): void {
    $duration = DurationData::from(['startDate' => '2026-10-02', 'startTime' => '09:00:00', 'endDate' => '2026-10-02', 'endTime' => '10:00:00']);
    $address  = new AddressData('PT', 'Example address');
    $route    = TransportRouteData::from(['locations' => [
        ['address' => $address, 'duration' => $duration, 'transportModeCode' => '3', 'vehicleRegistrationCode' => 'AB12'],
        ['address' => $address, 'duration' => $duration, 'transportModeCode' => '0'],
    ]]);
    expect($route->locations[0]->duration->startDate->format('Y-m-d'))->toBe('2026-10-02')
        ->and($duration->toArray()['startTime'])->toBe('09:00:00')
        ->and(new DeliveryData(CarbonImmutable::parse('2026-10-02'), $address)->toArray()['deliveryDate'])->toBe('2026-10-02');
});

it('rejects invalid or reversed date periods', function (array $payload, string $field, string $message): void {
    expect(fn (): DatePeriodData => DatePeriodData::from($payload))->toFailValidationOn($field, $message);
})->with([
    'impossible calendar date' => [['startDate' => '2026-02-30', 'endDate' => '2026-03-01'], 'startDate', 'The start date must use a valid fiscal date or time.'],
    'before the fiscal epoch'  => [['startDate' => '2020-12-31', 'endDate' => '2026-03-01'], 'startDate', 'The start date must use a valid fiscal date or time.'],
    'end before the start'     => [['startDate' => '2026-03-02', 'endDate' => '2026-03-01'], 'endDate', 'The end date field must be a date after or equal to startDate.'],
]);

it('requires an end time when a duration has an end date', function (): void {
    $payload = ['startDate' => '2026-10-02', 'startTime' => '09:00:00', 'endDate' => '2026-10-03'];

    expect(fn (): DurationData => DurationData::from($payload))->toFailValidationOn('endTime', 'The end time field is required when end date is present.');
});

it('requires at least two transport route locations', function (): void {
    $payload = ['locations' => []];

    expect(fn (): TransportRouteData => TransportRouteData::from($payload))->toFailValidationOn('locations', 'The locations field must have at least 2 items.');
});

it('models rent self billing contingency and custom values explicitly', function (): void {
    $rent        = RentReceiptData::from(FiscalValueFixtures::rentReceipt());
    $billing     = new SelfBillingData('12345678-1234-1234-1234-123456789012', '1234');
    $contingency = ContingencyData::from(['issueDate' => '2026-10-02', 'issueTime' => '09:00:00', 'reasonTypeCode' => '1', 'ledCode' => 1]);
    expect($rent->referencePeriod)->toBe('2026-10')
        ->and($billing->authorizationCode)->toBe('1234')
        ->and($contingency->toArray()['issueTime'])->toBe('09:00:00')
        ->and((new ExtraFieldData('CustomerTag', 'value'))->value)->toBe('value');
});

it('rejects incomplete contingency reserved extension names and malformed self billing', function (string $class, array $payload, string $field, string $message): void {
    expect(fn (): mixed => $class::from($payload))->toFailValidationOn($field, $message);
})->with(FiscalValueFixtures::invalidContingencyExtensionAndSelfBilling());
