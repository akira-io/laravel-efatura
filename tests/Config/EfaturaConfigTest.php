<?php

declare(strict_types=1);

use Akira\Efatura\Config\EfaturaConfig;
use Akira\Efatura\Contracts\DocumentTypePolicy;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Support\DefaultDocumentTypePolicy;

function makeConfig(array $overrides = []): EfaturaConfig
{
    $config = app('config');

    $config->set('efatura', array_replace_recursive([
        'transmitter' => [
            'nif' => '100200300',
            'led' => 'LED123',
        ],
        'software' => [
            'code'    => 'SW-001',
            'name'    => 'Efatura Suite',
            'version' => '1.0.0',
        ],
        'middleware' => [
            'base_url'    => 'https://middleware.example',
            'environment' => 'TEST',
        ],
    ], $overrides));

    return new EfaturaConfig($config);
}

it('defaults repository environment to TEST when empty', function (): void {
    $config = makeConfig([
        'middleware' => [
            'environment' => '',
        ],
    ]);

    expect($config->environment())->toBe(Environment::TEST)
        ->and($config->repositoryCode())->toBe(3);
});

it('maps repository environment codes', function (): void {
    expect(Environment::PRODUCTION->code())->toBe(1)
        ->and(Environment::HOMOLOGATION->code())->toBe(2)
        ->and(Environment::TEST->code())->toBe(3);
});

it('accepts environment by name', function (): void {
    $config = makeConfig([
        'middleware' => [
            'environment' => 'PRODUCTION',
        ],
    ]);

    expect($config->environment())->toBe(Environment::PRODUCTION)
        ->and($config->repositoryCode())->toBe(1);
});

it('accepts environment by numeric code', function (): void {
    $config = makeConfig([
        'middleware' => [
            'environment' => '2',
        ],
    ]);

    expect($config->environment())->toBe(Environment::HOMOLOGATION)
        ->and($config->repositoryCode())->toBe(2);
});

it('accepts environment by integer code', function (): void {
    $config = makeConfig([
        'middleware' => [
            'environment' => 2,
        ],
    ]);

    expect($config->environment())->toBe(Environment::HOMOLOGATION)
        ->and($config->repositoryCode())->toBe(2);
});

it('normalizes environment strings with whitespace', function (): void {
    $config = makeConfig([
        'middleware' => [
            'environment' => '  production  ',
        ],
    ]);

    expect($config->environment())->toBe(Environment::PRODUCTION)
        ->and($config->repositoryCode())->toBe(1);
});

it('returns configured values and asArray', function (): void {
    $config = makeConfig([
        'middleware' => [
            'environment' => Environment::HOMOLOGATION,
        ],
    ]);

    expect($config->transmitterNif())->toBe('100200300')
        ->and($config->transmitterLedCode())->toBe('LED123')
        ->and($config->softwareCode())->toBe('SW-001')
        ->and($config->softwareName())->toBe('Efatura Suite')
        ->and($config->softwareVersion())->toBe('1.0.0')
        ->and($config->middlewareBaseUrl())->toBe('https://middleware.example')
        ->and($config->environment())->toBe(Environment::HOMOLOGATION)
        ->and($config->repositoryCode())->toBe(2);

    $array = $config->asArray();

    expect($array['transmitter']['nif'])->toBe('100200300')
        ->and($array['software']['code'])->toBe('SW-001')
        ->and($array['middleware']['repository_code'])->toBe(2);
});

it('fails on invalid numeric environment', function (): void {
    $config = makeConfig([
        'middleware' => [
            'environment' => 99,
        ],
    ]);

    expect(fn (): Environment => $config->environment())
        ->toThrow(EfaturaValidationException::class, trans('efatura.config.environment_invalid'));
});

it('covers getString reflection branches', function (): void {
    $config = makeConfig();

    $reflection = new ReflectionMethod(EfaturaConfig::class, 'getString');

    expect($reflection->invoke($config, 'efatura.transmitter.nif'))->toBe('100200300');

    config(['efatura.transmitter.led' => ['invalid']]);

    expect($reflection->invoke($config, 'efatura.transmitter.led'))->toBe('');
});

it('rejects invalid environment values', function (): void {
    $config = makeConfig([
        'middleware' => [
            'environment' => 'INVALID',
        ],
    ]);

    expect(fn (): Environment => $config->environment())
        ->toThrow(EfaturaValidationException::class, trans('efatura.config.environment_invalid'));
});

it('requires transmitter nif', function (): void {
    $config = makeConfig([
        'transmitter' => [
            'nif' => '',
        ],
    ]);

    expect(fn (): string => $config->transmitterNif())
        ->toThrow(EfaturaValidationException::class, trans('efatura.config.transmitter_nif_required'));
});

it('requires transmitter LED code', function (): void {
    $config = makeConfig([
        'transmitter' => [
            'led' => '',
        ],
    ]);

    expect(fn (): string => $config->transmitterLedCode())
        ->toThrow(EfaturaValidationException::class, trans('efatura.config.transmitter_led_required'));
});

it('requires middleware base url', function (): void {
    $config = makeConfig([
        'middleware' => [
            'base_url' => '',
        ],
    ]);

    expect(fn (): string => $config->middlewareBaseUrl())
        ->toThrow(EfaturaValidationException::class, trans('efatura.config.middleware_base_url_required'));
});

it('covers getString int and empty branches', function (): void {
    $config = makeConfig([
        'transmitter' => [
            'nif' => 123,
            'led' => null,
        ],
    ]);

    expect($config->transmitterNif())->toBe('123');

    expect(fn (): string => $config->transmitterLedCode())
        ->toThrow(EfaturaValidationException::class, trans('efatura.config.transmitter_led_required'));
});

it('covers getString reflection branch', function (): void {
    $config = makeConfig();

    $reflection = new ReflectionMethod(EfaturaConfig::class, 'getString');

    expect($reflection->invoke($config, 'efatura.transmitter.nif'))->toBe('100200300');
});

it('resolves document type policy from the container', function (): void {
    $policy = app(DocumentTypePolicy::class);

    expect($policy)->toBeInstanceOf(DefaultDocumentTypePolicy::class);
});

it('requires software code', function (): void {
    $config = makeConfig([
        'software' => [
            'code' => '',
        ],
    ]);

    expect(fn (): string => $config->softwareCode())
        ->toThrow(EfaturaValidationException::class, trans('efatura.config.software_code_required'));
});

it('requires software name', function (): void {
    $config = makeConfig([
        'software' => [
            'name' => '',
        ],
    ]);

    expect(fn (): string => $config->softwareName())
        ->toThrow(EfaturaValidationException::class, trans('efatura.config.software_name_required'));
});

it('requires software version', function (): void {
    $config = makeConfig([
        'software' => [
            'version' => '',
        ],
    ]);

    expect(fn (): string => $config->softwareVersion())
        ->toThrow(EfaturaValidationException::class, trans('efatura.config.software_version_required'));
});

it('falls back to English translations', function (): void {
    config(['app.fallback_locale' => 'en']);
    app()->setLocale('fr');

    $config = makeConfig([
        'transmitter' => [
            'nif' => '',
        ],
    ]);

    expect(fn (): string => $config->transmitterNif())
        ->toThrow(EfaturaValidationException::class, trans('efatura.config.transmitter_nif_required', [], 'en'));

    app()->setLocale('en');
});
