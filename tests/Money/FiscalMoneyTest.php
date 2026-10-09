<?php

declare(strict_types=1);

use Akira\Efatura\Casts\MoneyCast;
use Akira\Efatura\Enums\DecimalViolation;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Money\FiscalMoney;
use Brick\Math\BigDecimal;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;

it('rounds fiscal amounts half up to two digits in CVE and foreign currencies on request', function (int|string $input, string $currency, string $expected): void {
    $amount = FiscalMoney::rounded($input, $currency);

    expect($amount->getCurrency()->getCurrencyCode())->toBe($currency)
        ->and(DecimalFormatter::money($amount))->toBe($expected);
})->with([
    [0, 'CVE', '0.00'],
    [12, 'CVE', '12.00'],
    ['1.234', 'CVE', '1.23'],
    ['1.235', 'CVE', '1.24'],
    ['-1.235', 'CVE', '-1.24'],
    ['999999999999999.995', 'USD', '1000000000000000.00'],
]);

it('rounds existing Money without changing its currency', function (): void {
    $source = Money::of('3.12500', 'USD', new CustomContext(5));

    expect(DecimalFormatter::money(FiscalMoney::rounded($source, 'USD')))->toBe('3.13')
        ->and(FiscalMoney::rounded('3.125', 'CVE')->getAmount()->isEqualTo('3.13'))->toBeTrue();
});

it('preserves a five-digit source amount until rounding is requested', function (): void {
    $source = FiscalMoney::exact('1.23456', 'CVE', 5);

    expect(DecimalFormatter::money($source, 5, false))->toBe('1.23456')
        ->and(DecimalFormatter::money(FiscalMoney::rounded($source, 'CVE')))->toBe('1.23');
});

it('creates two-place fiscal amounts without rounding', function (int|string|Money $input, string $expected): void {
    expect((string) FiscalMoney::cve($input)->getAmount())->toBe($expected)
        ->and((string) FiscalMoney::of($input, 'CVE')->getAmount())->toBe($expected);
})->with([
    'integer'               => [12, '12.00'],
    'two places'            => ['1.23', '1.23'],
    'insignificant zeros'   => ['1.23000', '1.23'],
    'five-place zero Money' => [Money::of('3.12000', 'CVE', new CustomContext(5)), '3.12'],
]);

it('rejects amounts beyond two places instead of rounding them', function (Closure $create): void {
    expect($create)->toFailValidationOn('lines.0.price', 'Value exceeds the allowed decimal precision.');
})->with([
    'cve text'      => [fn (): Money => FiscalMoney::cve('3.125', 'lines.0.price')],
    'of text'       => [fn (): Money => FiscalMoney::of('1.234', 'USD', 'lines.0.price')],
    'of five-place' => [fn (): Money => FiscalMoney::of(FiscalMoney::exact('1.23456', 'CVE'), 'CVE', 'lines.0.price')],
]);

it('accepts insignificant decimal zeros without losing exact precision boundaries', function (): void {
    expect((string) DecimalFormatter::parse('1.23000', 2))->toBe('1.23000')
        ->and(DecimalFormatter::decimal(BigDecimal::of('1.23000'), 2))->toBe('1.23')
        ->and(DecimalFormatter::decimal(BigDecimal::of('10.00000'), 2))->toBe('10')
        ->and(DecimalFormatter::decimal(BigDecimal::of('0.00000'), 2))->toBe('0')
        ->and(DecimalFormatter::money(FiscalMoney::exact('1.23000', 'CVE'), 2, false))->toBe('1.23');
});

it('rejects a significant trailing digit beyond the requested decimal scale', function (): void {
    $value = BigDecimal::of('1.23400');

    expect(fn (): string => DecimalFormatter::decimal($value, 2))
        ->toFailValidationOn('amount', 'Value exceeds the allowed decimal precision.');
});

it('rejects malformed amount text and unknown or badly cased currencies', function (string $amount, string $currency, string $message): void {
    expect(fn (): Money => FiscalMoney::of($amount, $currency))->toFailValidationOn('amount', $message);
})->with([
    'not a number'         => ['NaN', 'CVE', 'Value must be a plain decimal number.'],
    'scientific notation'  => ['1e3', 'CVE', 'Value must be a plain decimal number.'],
    'thousands separator'  => ['1,000.00', 'CVE', 'Value must be a plain decimal number.'],
    'four letter currency' => ['1.00', 'NOPE', 'Currency must be an uppercase code of the official currency catalog.'],
    'unknown currency'     => ['1.00', 'ZZZ', 'Currency must be an uppercase code of the official currency catalog.'],
    'mixed case currency'  => ['1.00', 'IdR', 'Currency must be an uppercase code of the official currency catalog.'],
    'ISO code outside XSD' => ['1.00', 'IDR', 'Currency must be an uppercase code of the official currency catalog.'],
]);

it('rejects a Money value with a different requested currency', function (): void {
    $source = Money::of('1.00', 'USD');

    expect(fn (): Money => FiscalMoney::cve($source))
        ->toFailValidationOn('amount', 'Money currency does not match the requested currency.');
});

it('rejects exact source precision beyond the owning field scale', function (): void {
    expect(fn (): Money => FiscalMoney::exact('1.234567', 'CVE', 5))
        ->toFailValidationOn('amount', 'Value exceeds the allowed decimal precision.');
});

it('rejects a decimal beyond the owning field scale', function (): void {
    $value = BigDecimal::of('1.2345');

    expect(fn (): string => DecimalFormatter::decimal($value, 3))
        ->toFailValidationOn('amount', 'Value exceeds the allowed decimal precision.');
});

it('does not accept a rounded Money cast with a non-fiscal scale', function (): void {
    expect(fn (): MoneyCast => new MoneyCast('CVE', 3, true))
        ->toThrow(DefinitionException::class, 'Rounded fiscal Money uses two decimal places, 3 given.');
});

it('rejects a Money scale above the five decimal XSD scale', function (): void {
    expect(fn (): Money => FiscalMoney::exact('1', 'CVE', 6))
        ->toThrow(DefinitionException::class, 'Money scale must be between 0 and 5, 6 given.');
});

it('rejects strict serialization that would lose digits', function (): void {
    $source = Money::of('1.23456', 'CVE', new CustomContext(5));

    expect(fn (): string => DecimalFormatter::money($source, 2, false))
        ->toFailValidationOn('amount', 'Value exceeds the allowed decimal precision.');
});

it('rejects a negative decimal scale', function (): void {
    $value = BigDecimal::one();

    expect(fn (): string => DecimalFormatter::decimal($value, -1))
        ->toThrow(DefinitionException::class, 'Decimal scale must not be negative, -1 given.');
});

it('reports programmatic failures at the field the caller names', function (Closure $call, string $errorCode): void {
    expect($call)->toThrow(function (EfaturaValidationException $exception) use ($errorCode): void {
        expect($exception->field())->toBe('lines.3.price')
            ->and($exception->errorCode)->toBe($errorCode);
    });
})->with([
    'malformed'         => [fn (): Money => FiscalMoney::cve('abc', 'lines.3.price'), 'decimal.invalid'],
    'excess scale'      => [fn (): Money => FiscalMoney::exact('1.123456', 'CVE', 5, 'lines.3.price'), 'decimal.scale_exceeded'],
    'float'             => [fn (): Money => FiscalMoney::of(1.5, 'CVE', 'lines.3.price'), 'money.invalid'],
    'unknown currency'  => [fn (): Money => FiscalMoney::of('1', 'ZZZ', 'lines.3.price'), 'money.invalid_currency'],
    'invalid currency'  => [fn (): Money => FiscalMoney::of('1', 'cve', 'lines.3.price'), 'money.invalid_currency'],
    'currency mismatch' => [fn (): Money => FiscalMoney::cve(Money::of('1', 'USD'), 'lines.3.price'), 'money.currency_mismatch'],
    'parse'             => [fn (): BigDecimal => DecimalFormatter::parse('1.234', 2, 'lines.3.price'), 'decimal.scale_exceeded'],
]);

it('answers decimal shape and scale questions without throwing', function (): void {
    expect(DecimalFormatter::isPlainDecimal('-1.50'))->toBeTrue()
        ->and(DecimalFormatter::isPlainDecimal(7))->toBeTrue()
        ->and(DecimalFormatter::isPlainDecimal(BigDecimal::of('1.5')))->toBeTrue()
        ->and(DecimalFormatter::isPlainDecimal('1e3'))->toBeFalse()
        ->and(DecimalFormatter::isPlainDecimal(1.5))->toBeFalse()
        ->and(DecimalFormatter::isPlainDecimal(null))->toBeFalse()
        ->and(DecimalFormatter::fitsScale(BigDecimal::of('1.23000'), 2))->toBeTrue()
        ->and(DecimalFormatter::fitsScale(BigDecimal::of('1.234'), 2))->toBeFalse();
});

it('names the first decimal violation of any input', function (mixed $value, ?int $scale, ?DecimalViolation $violation): void {
    expect(DecimalFormatter::violation($value, $scale))->toBe($violation);
})->with([
    'plain within scale'     => ['1.23000', 2, null],
    'unbounded scale'        => ['1.123456789', null, null],
    'array'                  => [['1'], 2, DecimalViolation::InvalidDecimal],
    'float'                  => [1.5, 2, DecimalViolation::InvalidDecimal],
    'sixteen integer digits' => [str_repeat('9', 16) . '.123', 2, DecimalViolation::IntegerDigitsExceeded],
    'scale exceeded'         => ['1.234', 2, DecimalViolation::ScaleExceeded],
]);

it('rejects input that is not a plain decimal when parsing', function (): void {
    expect(fn (): BigDecimal => DecimalFormatter::parse(['1'], 2, 'lines.0.price'))
        ->toFailValidationOn('lines.0.price', 'Value must be a plain decimal number.');
});
