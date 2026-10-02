<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Brick\Money\Money;

it('rejects float amounts before a non-strict caller can coerce them', function (string $entry, array $arguments, string $message, float $amount): void {
    $call = (require __DIR__ . '/../Fixtures/NonStrictNumericCaller.inc')[$entry];

    expect(fn (): Money|BigDecimal => $call($amount, ...$arguments))->toFailValidationOn('amount', $message);
})->with([
    'cve'   => ['cve', [], 'Money amount or currency is invalid.'],
    'of'    => ['of', ['USD'], 'Money amount or currency is invalid.'],
    'exact' => ['exact', [], 'Money amount or currency is invalid.'],
    'parse' => ['parse', [], 'Value must be a plain decimal number.'],
])->with([1.99, 2.0]);

it('retains exact accepted inputs from a non-strict caller', function (): void {
    $caller = require __DIR__ . '/../Fixtures/NonStrictNumericCaller.inc';

    expect((string) $caller['cve'](2)->getAmount())->toBe('2.00')
        ->and((string) $caller['of']('1.235', 'USD')->getAmount())->toBe('1.24')
        ->and((string) $caller['exact']('1.23456')->getAmount())->toBe('1.23456')
        ->and((string) $caller['parse']('1.23000'))->toBe('1.23000')
        ->and((string) $caller['parse'](BigDecimal::of('1.23')))->toBe('1.23');
});
