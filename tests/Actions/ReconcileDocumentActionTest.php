<?php

declare(strict_types=1);

use Akira\Efatura\Actions\PrepareDocumentAction;
use Akira\Efatura\Actions\ReconcileDocumentAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\ReconciliationOutcome;
use Akira\Efatura\Issuance\Reconciliation;
use Akira\Efatura\Packaging\PreparedDocument;
use Akira\Efatura\Testing\FakeTransport;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\RecordingAccessTokens;
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

function reconcileOnThePlatform(): RecordingAccessTokens
{
    $tokens = new RecordingAccessTokens(bin2hex(random_bytes(12)));
    app()->instance(AccessTokenProvider::class, $tokens);
    app()->instance(EfaturaConfig::class, ConfigFixtures::load([
        'driver'  => 'platform',
        'emitter' => ['tax_id' => '500600700'],
        'http'    => ['retry_delay_milliseconds' => 0],
    ]));
    Http::fake(['*' => Http::response('', 404)]);

    return $tokens;
}

it('looks a prepared document up with the token its archive calls for', function (bool $selfBilled, string $taxpayer): void {
    P::configure(config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    $prepared = resolve(PrepareDocumentAction::class)->handle(P::invoice());
    $archive  = T::archive([$prepared->iud], selfBilled: $selfBilled);
    $document = new PreparedDocument($prepared->document, $prepared->iud, $prepared->unsignedXml, $prepared->signed, $archive, $prepared->allocated);
    $tokens   = reconcileOnThePlatform();

    $reconciliation = resolve(ReconcileDocumentAction::class)->handle($document);

    expect($reconciliation)->toEqual(new Reconciliation($prepared->iud, ReconciliationOutcome::NotFound, null))
        ->and($tokens->calls)->toBe([['token', $taxpayer, ['cv_ef_dfe_read_iud']]]);
})->with([
    'self-billed, with the token of the buyer' => [true, '500600700'],
    'regular, with the token of the seller'    => [false, '100200300'],
]);

it('looks an iud up with the token of the buyer when the caller says it is self-billed', function (): void {
    $tokens = reconcileOnThePlatform();

    expect(resolve(ReconcileDocumentAction::class)->handle(T::FIRST_IUD, selfBilled: true)->authorized())->toBeFalse()
        ->and($tokens->calls)->toBe([['token', '500600700', ['cv_ef_dfe_read_iud']]]);
});
