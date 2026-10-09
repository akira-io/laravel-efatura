<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Tests\Fixtures\FiscalValuesData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Brick\Math\BigDecimal;
use Brick\Money\Money;

it('rejects a sixteen digit integer part in every document amount and quantity', function (array $overrides, string $field): void {
    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload($overrides)))
        ->toFailValidationOn($field, 'Value exceeds the allowed 15 integer digits.');
})->with([
    'price'           => [['lines' => [F::linePayload(['price' => '1234567890123456'])]], 'lines.0.price'],
    'price extension' => [['lines' => [F::linePayload(['priceExtension' => '-1234567890123456.5'])]], 'lines.0.priceExtension'],
    'quantity'        => [['lines' => [F::linePayload(['quantity' => ['value' => '1234567890123456', 'unitCode' => 'C62']])]], 'lines.0.quantity.value'],
    'payable'         => [['totals' => F::totalsPayload(['payableAmount' => '1234567890123456'])], 'totals.payableAmount'],
    'rounding'        => [['totals' => F::totalsPayload(['payableRoundingAmount' => '-1234567890123456'])], 'totals.payableRoundingAmount'],
]);

it('rejects a fifty thousand digit price before any arithmetic', function (): void {
    $payload = F::payload(['lines' => [F::linePayload(['price' => str_repeat('9', 50000), 'quantity' => ['value' => str_repeat('9', 50000), 'unitCode' => 'C62']])]]);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))
        ->toFailValidationOn('lines.0.price', 'Value exceeds the allowed 15 integer digits.')
        ->and(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))
        ->toFailValidationOn('lines.0.quantity.value', 'Value exceeds the allowed 15 integer digits.');
});

it('accepts fifteen integer digits with five decimal places', function (): void {
    $amount = '123456789012345.12345';

    expect((string) QuantityData::from(['value' => $amount, 'unitCode' => 'C62'])->value)->toBe($amount)
        ->and((string) FiscalMoney::exact($amount, 'CVE')->getAmount())->toBe($amount)
        ->and(DecimalFormatter::fitsIntegerDigits(BigDecimal::of('-999999999999999.99999')))->toBeTrue()
        ->and(DecimalFormatter::fitsIntegerDigits(BigDecimal::of('0000000000000001234567890123456')))->toBeFalse();
});

it('rejects a sixteen digit exchange rate', function (): void {
    expect(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::from(['value' => '1', 'currencyCode' => 'EUR', 'exchangeRate' => '1234567890123456']))
        ->toFailValidationOn('exchangeRate', 'Value exceeds the allowed 15 integer digits.');
});

it('rejects a sixteen digit integer part in money construction at the given field', function (Closure $create): void {
    expect($create)->toFailValidationOn('lines.0.price', 'Value exceeds the allowed 15 integer digits.');
})->with([
    'exact'   => [fn (): Money => FiscalMoney::exact('1234567890123456', 'CVE', field: 'lines.0.price')],
    'rounded' => [fn (): Money => FiscalMoney::rounded('1234567890123456.555', 'CVE', 'lines.0.price')],
    'Money'   => [fn (): Money => FiscalMoney::cve(Money::of('1234567890123456', 'CVE'), 'lines.0.price')],
    'integer' => [fn (): Money => FiscalMoney::cve(1234567890123456, 'lines.0.price')],
]);

it('rejects a sixteen digit integer part in an unvalidated decimal cast', function (): void {
    $payload = ['payable' => '1', 'unitPrice' => '1', 'exchangeRate' => '1234567890123456', 'taxPercentage' => '1'];

    expect(fn (): FiscalValuesData => FiscalValuesData::from($payload))->toFailValidationOn('exchangeRate', 'Value exceeds the allowed 15 integer digits.');
});
