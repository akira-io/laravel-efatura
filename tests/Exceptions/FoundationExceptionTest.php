<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Exceptions\OfficialArtifactException;
use Akira\Efatura\Support\OfficialArtifacts;

it('exposes configuration failures through the package base', function (): void {
    $exception = new ConfigurationException('configuration.unsafe_url', 'efatura.http.platform.base_url');

    expect($exception)->toBeInstanceOf(EfaturaException::class)
        ->and($exception->errorCode)->toBe('configuration.unsafe_url')
        ->and($exception->field)->toBe('efatura.http.platform.base_url')
        ->and($exception->context)->toBe([])
        ->and($exception->retryable)->toBeFalse();
});

it('preserves validation field access while exposing the package base', function (): void {
    $exception = new EfaturaValidationException('tax_id', 'Invalid taxpayer identity.');

    expect($exception)->toBeInstanceOf(EfaturaException::class)
        ->and($exception->errorCode)->toBe('validation.invalid_value')
        ->and($exception->field())->toBe('tax_id')
        ->and($exception->getMessage())->toBe('Invalid taxpayer identity.');
});

it('provides a typed safe artifact failure for unknown profiles', function (): void {
    try {
        new OfficialArtifacts()->xsdEntry('untrusted-profile-secret');
        test()->fail('Unknown profile was accepted.');
    } catch (OfficialArtifactException $officialArtifactException) {
        expect($officialArtifactException)->toBeInstanceOf(EfaturaException::class)
            ->and($officialArtifactException->errorCode)->toBe('artifacts.unknown_profile')
            ->and($officialArtifactException->context)->toBe(['operation' => 'xsd_entry'])
            ->and($officialArtifactException->field)->toBeNull()
            ->and($officialArtifactException->getMessage())->not->toContain('untrusted-profile-secret');
    }
});
