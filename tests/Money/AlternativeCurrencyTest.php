<?php

declare(strict_types=1);

use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Money\CatalogCurrency;
use Akira\Efatura\Money\FiscalMoney;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;

it('accepts every official alternative currency the XSD enumerates', function (string $currency): void {
    $amount = PayableAlternativeAmountData::from(['value' => '1.5', 'currencyCode' => $currency, 'exchangeRate' => '150']);

    expect($amount->value->getCurrency()->getCurrencyCode())->toBe($currency)
        ->and($amount->toArray())->toBe(['value' => '1.50000', 'currencyCode' => $currency, 'exchangeRate' => '150']);
})->with(['EUR', 'IdR', 'XAG', 'XAU', 'XBA', 'XBB', 'XBC', 'XBD', 'XDR', 'XPD', 'XPT', 'XSU', 'XTS', 'XUA', 'XXX']);

it('rejects a currency outside the official enumeration', function (string $currency): void {
    expect(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::from(['value' => '1', 'currencyCode' => $currency, 'exchangeRate' => '150']))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['currencyCode' => ['The currency code must be a code in the official catalog.']]);
        });
})->with(['IDR', 'ZZZ']);

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
    expect(fn (): Money => FiscalMoney::exact(FiscalMoney::of('1', 'USD'), CatalogCurrency::of('XDR')))
        ->toThrow(EfaturaValidationException::class, 'Money currency does not match the requested currency.');
});
