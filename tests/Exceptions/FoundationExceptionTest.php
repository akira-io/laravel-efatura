<?php

declare(strict_types=1);

use Akira\Efatura\Casts\MoneyCast;
use Akira\Efatura\Exceptions\CatalogException;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Exceptions\OfficialArtifactException;
use Akira\Efatura\Exceptions\ResourceException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Support\OfficialArtifacts;
use Brick\Math\BigDecimal;
use Brick\Money\Money;

it('exposes configuration failures through the package base', function (): void {
    $exception = new ConfigurationException('configuration.unsafe_url', 'efatura.http.platform.base_url');

    expect($exception)->toBeInstanceOf(EfaturaException::class)
        ->and($exception->errorCode)->toBe('configuration.unsafe_url')
        ->and($exception->field)->toBe('efatura.http.platform.base_url')
        ->and($exception->context)->toBe([])
        ->and($exception->retryable)->toBeFalse();
});

it('preserves validation field access while exposing the package base', function (): void {
    $exception = EfaturaValidationException::invalidDecimal('amount');

    expect($exception)->toBeInstanceOf(EfaturaException::class)
        ->and($exception->errorCode)->toBe('decimal.invalid')
        ->and($exception->field())->toBe('amount');
});

it('reports definition errors through the package base without translation', function (Closure $definition, string $errorCode, string $message): void {
    expect($definition)->toThrow(function (DefinitionException $exception) use ($errorCode, $message): void {
        expect($exception)->toBeInstanceOf(EfaturaException::class)
            ->and($exception->errorCode)->toBe($errorCode)
            ->and($exception->getMessage())->toBe($message);
    });
})->with([
    'negative decimal scale' => [fn (): string => DecimalFormatter::decimal(BigDecimal::one(), -1), 'definition.negative_scale', 'Decimal scale must not be negative, -1 given.'],
    'money scale'            => [fn (): Money => FiscalMoney::exact('1', 'CVE', 6), 'definition.money_scale', 'Money scale must be between 0 and 5, 6 given.'],
    'rounding scale'         => [fn (): MoneyCast => new MoneyCast('CVE', 3, true), 'definition.rounding_scale', 'Rounded fiscal Money uses two decimal places, 3 given.'],
]);

it('provides a typed safe artifact failure for unknown profiles', function (): void {
    $artifacts = resolve(OfficialArtifacts::class);

    expect(fn (): string => $artifacts->xsdEntry('untrusted-profile-secret'))
        ->toThrow(function (OfficialArtifactException $exception): void {
            expect($exception)->toBeInstanceOf(EfaturaException::class)
                ->and($exception->errorCode)->toBe('artifacts.unknown_profile')
                ->and($exception->context)->toBe(['operation' => 'xsd_entry'])
                ->and($exception->field)->toBeNull()
                ->and($exception->getMessage())->not->toContain('untrusted-profile-secret');
        });
});

it('shares one packaged resource failure shape across catalogs and artifacts', function (string $exceptionClass): void {
    $previous  = new RuntimeException('disk failure');
    $exception = new $exceptionClass('resource.missing_or_unreadable', 'load', $previous);

    expect($exception)->toBeInstanceOf(ResourceException::class)
        ->and($exception->errorCode)->toBe('resource.missing_or_unreadable')
        ->and($exception->getMessage())->toBe('resource.missing_or_unreadable')
        ->and($exception->context)->toBe(['operation' => 'load'])
        ->and($exception->getPrevious())->toBe($previous);
})->with([CatalogException::class, OfficialArtifactException::class]);
