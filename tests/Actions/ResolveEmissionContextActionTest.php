<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ResolveEmissionContextAction;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;

beforeEach(function (): void {
    P::configure();
});

it('builds a missing emission context from the transmitter and software configuration', function (): void {
    $resolved = resolve(ResolveEmissionContextAction::class)->handle(null);

    expect($resolved->issueMode)->toBe(EmissionMode::Online)
        ->and($resolved->contingency)->toBeNull()
        ->and($resolved->transmitterTaxId?->value)->toBe(P::TRANSMITTER)
        ->and($resolved->transmitterTaxId?->countryCode)->toBe('CV')
        ->and($resolved->software?->toArray())->toBe(['code' => 'APP', 'name' => 'Fiscal App', 'version' => '1.0']);
});

it('never mixes the configuration into an explicit emission context', function (): void {
    $explicit = EmissionContextData::from(['transmitterTaxId' => ['value' => '900800700', 'countryCode' => 'CV']]);
    $resolved = resolve(ResolveEmissionContextAction::class)->handle($explicit);

    expect($resolved)->toBe($explicit)
        ->and($resolved->software)->toBeNull()
        ->and($resolved->transmitterTaxId?->value)->toBe('900800700');
});

it('reports the configuration key that a missing emission context needs', function (string $key): void {
    P::configure(config: [$key => null]);
    $explicit = EmissionContextData::from(DocumentPayloads::transmission());

    expect(fn (): EmissionContextData => resolve(ResolveEmissionContextAction::class)->handle(null))
        ->toThrow(function (ConfigurationException $exception) use ($key): void {
            expect($exception->errorCode)->toBe('configuration.missing')
                ->and($exception->field)->toBe($key)
                ->and($exception->getMessage())->toBe('configuration.missing: ' . $key);
        })
        ->and(resolve(ResolveEmissionContextAction::class)->handle($explicit))->toBe($explicit);
})->with(['efatura.transmitter.tax_id', 'efatura.software.code', 'efatura.software.name', 'efatura.software.version']);
