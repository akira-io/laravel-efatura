<?php

declare(strict_types=1);

use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Money\CatalogCurrency;
use Akira\Efatura\Money\FiscalMoney;
use Brick\Money\Currency;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;

it('accepts every official alternative currency the XSD enumerates', function (string $currency): void {
    $amount = PayableAlternativeAmountData::from(['value' => '1.5', 'currencyCode' => $currency, 'exchangeRate' => '150']);

    expect($amount->value->getCurrency()->getCurrencyCode())->toBe($currency)
        ->and($amount->toArray())->toBe(['value' => '1.50000', 'currencyCode' => $currency, 'exchangeRate' => '150']);
})->with(['EUR', 'XAG', 'XAU', 'XBA', 'XBB', 'XBC', 'XBD', 'XDR', 'XPD', 'XPT', 'XSU', 'XTS', 'XUA', 'XXX']);

it('rejects a currency outside the official enumeration', function (string $currency): void {
    $payload = ['value' => '1', 'currencyCode' => $currency, 'exchangeRate' => '150'];

    expect(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::from($payload))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['currencyCode' => ['The currency code must be a code in the official catalog.']]);
        });
})->with(['IdR', 'IDR', 'ZZZ']);

it('builds fiscal money in a catalog currency that Brick does not ship', function (): void {
    expect(FiscalMoney::exact('1.12345', 'XDR')->getCurrency()->getCurrencyCode())->toBe('XDR');
});

it('rejects fiscal money in a currency outside the official enumeration', function (string $currency): void {
    expect(fn (): Money => FiscalMoney::of('1', $currency))
        ->toFailValidationOn('amount', 'Currency must be an uppercase code of the official currency catalog.');
})->with(['IdR', 'IDR', 'ZZZ']);

it('rejects a catalog currency lookup outside the official enumeration', function (): void {
    expect(fn (): Currency => CatalogCurrency::of('IDR', 'currencyCode'))
        ->toFailValidationOn('currencyCode', 'Currency must be an uppercase code of the official currency catalog.');
});

it('resolves ISO currencies through Brick and other catalog codes as custom currencies', function (): void {
    $euro = CatalogCurrency::of('EUR');
    $sdr  = CatalogCurrency::of('XDR');

    expect($euro->getNumericCode())->toBe(978)
        ->and($euro->getDefaultFractionDigits())->toBe(2)
        ->and($sdr->getCurrencyCode())->toBe('XDR')
        ->and($sdr->getNumericCode())->toBe(0)
        ->and($sdr->getDefaultFractionDigits())->toBe(5)
        ->and(FiscalMoney::exact('1.12345', $sdr)->getAmount()->__toString())->toBe('1.12345');
});

it('rejects money in another currency than the resolved catalog currency', function (): void {
    $dollars = FiscalMoney::of('1', 'USD');
    $sdr     = CatalogCurrency::of('XDR');

    expect(fn (): Money => FiscalMoney::exact($dollars, $sdr))
        ->toFailValidationOn('amount', 'Money currency does not match the requested currency.');
});
