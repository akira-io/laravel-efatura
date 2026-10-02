<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Money\MoneyCast;
use Brick\Math\BigDecimal;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;

it('normalizes fiscal amounts half up to two digits in CVE and foreign currencies', function (int|string $input, string $currency, string $expected): void {
    $amount = FiscalMoney::of($input, $currency);

    expect($amount->getCurrency()->getCurrencyCode())->toBe($currency)
        ->and(DecimalFormatter::money($amount))->toBe($expected);
})->with([
    [0, 'CVE', '0.00'],
    [12, 'CVE', '12.00'],
    ['1.234', 'CVE', '1.23'],
    ['1.235', 'CVE', '1.24'],
    ['-1.235', 'CVE', '-1.24'],
    ['999999999999999999999999.995', 'USD', '1000000000000000000000000.00'],
]);

it('normalizes existing Money without changing its currency', function (): void {
    $source = Money::of('3.12500', 'USD', new CustomContext(5));

    expect(DecimalFormatter::money(FiscalMoney::of($source, 'USD')))->toBe('3.13')
        ->and(DecimalFormatter::money(FiscalMoney::cve('3.125')))->toBe('3.13');
});

it('preserves a five-digit source amount until fiscal normalization is requested', function (): void {
    $source = FiscalMoney::exact('1.23456', 'CVE', 5);

    expect(DecimalFormatter::money($source, 5, false))->toBe('1.23456')
        ->and(DecimalFormatter::money(FiscalMoney::cve($source)))->toBe('1.23');
});

it('accepts insignificant decimal zeros without losing exact precision boundaries', function (): void {
    expect((string) DecimalFormatter::parse('1.23000', 2))->toBe('1.23000')
        ->and(DecimalFormatter::decimal(BigDecimal::of('1.23000'), 2))->toBe('1.23')
        ->and(DecimalFormatter::decimal(BigDecimal::of('10.00000'), 2))->toBe('10')
        ->and(DecimalFormatter::decimal(BigDecimal::of('0.00000'), 2))->toBe('0')
        ->and(DecimalFormatter::money(FiscalMoney::exact('1.23000', 'CVE'), 2, false))->toBe('1.23');
    expect(fn (): string => DecimalFormatter::decimal(BigDecimal::of('1.23400'), 2))
        ->toThrow(EfaturaValidationException::class);
});

it('rejects malformed amount text and unknown or mismatched currencies', function (string $amount, string $currency): void {
    expect(fn (): Money => FiscalMoney::of($amount, $currency))
        ->toThrow(EfaturaValidationException::class);
})->with([
    ['NaN', 'CVE'],
    ['1e3', 'CVE'],
    ['1,000.00', 'CVE'],
    ['1.00', 'NOPE'],
    ['1.00', 'ZZZ'],
    ['1.00', 'IdR'],
]);

it('rejects a Money value with a different requested currency', function (): void {
    $source = Money::of('1.00', 'USD');

    expect(fn (): Money => FiscalMoney::cve($source))
        ->toThrow(EfaturaValidationException::class);
});

it('rejects source precision beyond the owning field scale', function (): void {
    expect(fn (): Money => FiscalMoney::exact('1.234567', 'CVE', 5))
        ->toThrow(EfaturaValidationException::class)
        ->and(fn (): string => DecimalFormatter::decimal(BigDecimal::of('1.2345'), 3))
        ->toThrow(EfaturaValidationException::class);
});

it('does not accept a rounded Money cast with a non-fiscal scale', function (): void {
    expect(fn (): MoneyCast => new MoneyCast('CVE', 3, true))
        ->toThrow(EfaturaValidationException::class);
});

it('rejects invalid precision policies and strict serialization that would lose digits', function (): void {
    $source = Money::of('1.23456', 'CVE', new CustomContext(5));

    expect(fn (): Money => FiscalMoney::exact('1', 'CVE', 6))
        ->toThrow(EfaturaValidationException::class)
        ->and(fn (): string => DecimalFormatter::money($source, 2, false))
        ->toThrow(EfaturaValidationException::class)
        ->and(fn (): string => DecimalFormatter::decimal(BigDecimal::one(), -1))
        ->toThrow(EfaturaValidationException::class);
});
