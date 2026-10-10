<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ResolveEmissionContextAction;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;

beforeEach(function (): void {
    P::configure();
});

it('fills a missing emission context from the transmitter and software configuration', function (Closure $data): void {
    $resolved = resolve(ResolveEmissionContextAction::class)->handle($data());

    expect($resolved->emission?->issueMode)->toBe(EmissionMode::Online)
        ->and($resolved->emission?->contingency)->toBeNull()
        ->and($resolved->emission?->transmitterTaxId?->value)->toBe(P::TRANSMITTER)
        ->and($resolved->emission?->transmitterTaxId?->countryCode)->toBe('CV')
        ->and($resolved->emission?->software?->toArray())->toBe(['code' => 'APP', 'name' => 'Fiscal App', 'version' => '1.0'])
        ->and($resolved->toPayload())->toBe([...$data()->toPayload(), 'emission' => $resolved->emission?->toPayload()]);
})->with([
    'document' => [fn (): DocumentData => P::invoice()],
    'event'    => [fn (): EventData => P::event()],
]);

it('never mixes the configuration into an explicit emission context', function (Closure $data): void {
    $explicit = $data();
    $resolved = resolve(ResolveEmissionContextAction::class)->handle($explicit);

    expect($resolved)->toBe($explicit)
        ->and($resolved->emission?->software)->toBeNull()
        ->and($resolved->emission?->transmitterTaxId?->value)->toBe('900800700');
})->with([
    'document' => [fn (): DocumentData => P::invoice(overrides: ['emission' => ['transmitterTaxId' => ['value' => '900800700', 'countryCode' => 'CV']]])],
    'event'    => [fn (): EventData => P::event(['emission' => ['transmitterTaxId' => ['value' => '900800700', 'countryCode' => 'CV']]])],
]);

it('reports the configuration key that a missing emission context needs', function (string $key): void {
    P::configure(config: [$key => null]);

    expect(fn (): DocumentData => resolve(ResolveEmissionContextAction::class)->handle(P::invoice()))
        ->toThrow(function (ConfigurationException $exception) use ($key): void {
            expect($exception->errorCode)->toBe('configuration.missing')
                ->and($exception->field)->toBe($key);
        });

    expect(resolve(ResolveEmissionContextAction::class)->handle(P::invoice(overrides: ['emission' => DocumentPayloads::transmission()]))->emission)
        ->toBeInstanceOf(EmissionContextData::class);
})->with(['efatura.transmitter.tax_id', 'efatura.software.code', 'efatura.software.name', 'efatura.software.version']);
