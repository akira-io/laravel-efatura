<?php

use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Money\FiscalMoney;
use Brick\Math\BigDecimal;
use Brick\Money\Money;

return [
    'cve' => static fn (int|float|string|Money $amount): Money => FiscalMoney::cve($amount),
    'of' => static fn (int|float|string|Money $amount, string $currency): Money => FiscalMoney::of($amount, $currency),
    'exact' => static fn (int|float|string|Money $amount): Money => FiscalMoney::exact($amount, 'CVE'),
    'parse' => static fn (int|float|string|BigDecimal $amount): BigDecimal => DecimalFormatter::parse($amount),
];
