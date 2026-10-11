<?php

declare(strict_types=1);

use Akira\Efatura\Dfa\DfaFormat;
use Brick\Math\BigDecimal;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;

it('writes CVE amounts the way the Manual prints them', function (string $amount, string $expected): void {
    expect(DfaFormat::cve(Money::of($amount, 'CVE')))->toBe($expected);
})->with([
    'zero'      => ['0', '0$00'],
    'hundreds'  => ['210', '210$00'],
    'thousands' => ['1400', '1 400$00'],
    'millions'  => ['1234567.8', '1 234 567$80'],
    'cents'     => ['0.05', '0$05'],
    'negative'  => ['-0.01', '-0$01'],
    'large'     => ['-1000000', '-1 000 000$00'],
]);

it('writes a unit price with its significant decimals, between two and five', function (string $price, string $expected): void {
    expect(DfaFormat::unitPrice(Money::of($price, 'CVE', new CustomContext(5))))->toBe($expected);
})->with([
    'whole'          => ['560', '560$00'],
    'cents'          => ['0.5', '0$50'],
    'three decimals' => ['0.125', '0$125'],
    'five decimals'  => ['0.00123', '0$00123'],
    'trailing zeros' => ['1400.12300', '1 400$123'],
    'negative'       => ['-2.50010', '-2$5001'],
]);

it('rounds CVE amounts half up to the cent', function (string $amount, string $expected): void {
    expect(DfaFormat::cve(Money::of($amount, 'CVE', new CustomContext(5))))->toBe($expected);
})->with([
    'half up'  => ['1.005', '1$01'],
    'below'    => ['1.00499', '1$00'],
    'negative' => ['-1.005', '-1$01'],
]);

it('writes foreign amounts with a decimal comma and their currency', function (Money $amount, string $expected): void {
    expect(DfaFormat::foreign($amount))->toBe($expected);
})->with([
    'euro' => [Money::of('1043.2', 'EUR'), '1 043,20 EUR'],
    'yen'  => [Money::of('16480', 'JPY'), '16 480 JPY'],
]);

it('writes the exchange rate as CVE per one unit at five decimals', function (string $rate, string $currency, string $expected): void {
    expect(DfaFormat::rate(BigDecimal::of($rate), $currency))->toBe($expected);
})->with([
    ['110.265', 'EUR', '1 EUR = 110,26500 CVE'],
    ['0.743215', 'JPY', '1 JPY = 0,74322 CVE'],
    ['1250', 'XAU', '1 XAU = 1 250,00000 CVE'],
]);

it('writes quantities and percentages without non-significant zeros', function (): void {
    expect(DfaFormat::quantity(BigDecimal::of('2.500')))->toBe('2,5')
        ->and(DfaFormat::quantity(BigDecimal::of('3')))->toBe('3')
        ->and(DfaFormat::percentage(BigDecimal::of('15.00')))->toBe('15%')
        ->and(DfaFormat::percentage(BigDecimal::of('7.5')))->toBe('7,5%');
});
