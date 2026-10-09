<?php

declare(strict_types=1);

use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Money\FiscalMoney;
use Brick\Math\BigDecimal;
use Brick\Money\Money;

it('renders package validation errors from actual translations', function (): void {
    expect(fn (): Money => FiscalMoney::of('1', 'bad'))
        ->toFailValidationOn('amount', 'Currency must be an uppercase code of the official currency catalog.');
});

it('renders numeric precision errors from package translations', function (): void {
    $value = BigDecimal::of('1.234');

    expect(fn (): string => DecimalFormatter::decimal($value, 2))
        ->toFailValidationOn('amount', 'Value exceeds the allowed decimal precision.');
});
