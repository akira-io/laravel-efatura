<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ReconcileDocumentAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\ReconciliationOutcome;
use Akira\Efatura\Issuance\Reconciliation;
use Akira\Efatura\Testing\FakeTransport;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    app()->instance(EfaturaConfig::class, ConfigFixtures::load(['transmitter' => ['middleware_key' => str_repeat('k', 64)]]));
});

it('reconciles a document the platform holds as authorized', function (): void {
    $xml = '<Dfe xmlns="urn:cv:efatura:xsd:v1.0" Id="' . T::FIRST_IUD . '"/>';
    Http::fake(['*' => Http::response($xml)]);

    $reconciliation = resolve(ReconcileDocumentAction::class)->handle(T::FIRST_IUD);

    expect($reconciliation)->toEqual(new Reconciliation(T::FIRST_IUD, ReconciliationOutcome::Authorized, $xml))
        ->and($reconciliation->authorized())->toBeTrue();
});

it('reconciles a document the platform does not hold as not found', function (): void {
    $fake = resolve(FakeTransport::class);
    app()->instance(Transport::class, $fake);

    $reconciliation = resolve(ReconcileDocumentAction::class)->handle(T::FIRST_IUD);

    expect($reconciliation)->toEqual(new Reconciliation(T::FIRST_IUD, ReconciliationOutcome::NotFound, null))
        ->and($reconciliation->authorized())->toBeFalse()
        ->and($fake->lookedUp())->toBe([T::FIRST_IUD]);
});

it('validates the iud before looking anything up', function (): void {
    $fake = resolve(FakeTransport::class);
    app()->instance(Transport::class, $fake);

    expect(fn (): Reconciliation => resolve(ReconcileDocumentAction::class)->handle(substr(T::FIRST_IUD, 0, -1) . '0'))
        ->toFailValidationOn('iud', 'The iud must be an official IUD with a valid check digit.')
        ->and($fake->lookedUp())->toBe([]);
});
