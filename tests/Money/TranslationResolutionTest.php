<?php

declare(strict_types=1);

use Akira\Efatura\Commands\InstallCommand;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Money\FiscalMoney;
use Brick\Math\BigDecimal;

it('renders package validation errors and install text from actual translations', function (): void {
    try {
        FiscalMoney::of('1', 'bad');
        test()->fail('Expected invalid currency');
    } catch (EfaturaValidationException $efaturaValidationException) {
        expect($efaturaValidationException->getMessage())->toBe('Currency must be a supported uppercase ISO code.')
            ->and($efaturaValidationException->getMessage())->not->toBe('efatura::efatura.validation.invalid_currency');
    }

    expect(resolve(InstallCommand::class)->getDescription())->toBe('Install akira/efatura configuration');
});

it('renders numeric precision errors from package translations', function (): void {
    try {
        DecimalFormatter::decimal(BigDecimal::of('1.234'), 2);
        test()->fail('Expected decimal precision error');
    } catch (EfaturaValidationException $efaturaValidationException) {
        expect($efaturaValidationException->getMessage())->toBe('Value exceeds the allowed decimal precision.');
    }
});
