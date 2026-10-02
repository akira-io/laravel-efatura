<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Brick\Math\BigDecimal;
use Brick\Money\Money;

it('rejects float amounts before a non-strict caller can coerce them', function (float $amount): void {
    $caller = require __DIR__ . '/../Fixtures/NonStrictNumericCaller.inc';

    expect(fn (): Money => $caller['cve']($amount))->toThrow(EfaturaValidationException::class)
        ->and(fn (): Money => $caller['of']($amount, 'USD'))->toThrow(EfaturaValidationException::class)
        ->and(fn (): Money => $caller['exact']($amount))->toThrow(EfaturaValidationException::class)
        ->and(fn (): BigDecimal => $caller['parse']($amount))->toThrow(EfaturaValidationException::class);
})->with([1.99, 2.0]);

it('retains exact accepted inputs from a non-strict caller', function (): void {
    $caller = require __DIR__ . '/../Fixtures/NonStrictNumericCaller.inc';

    expect((string) $caller['cve'](2)->getAmount())->toBe('2.00')
        ->and((string) $caller['of']('1.235', 'USD')->getAmount())->toBe('1.24')
        ->and((string) $caller['exact']('1.23456')->getAmount())->toBe('1.23456')
        ->and((string) $caller['parse']('1.23000'))->toBe('1.23000')
        ->and((string) $caller['parse'](BigDecimal::of('1.23')))->toBe('1.23');
});
