<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\CertificateFixtures;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\RouteFixtures;
use Akira\Efatura\Tests\Support\SequenceFixtures;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    RouteFixtures::enable();
    P::configure('pem-encrypted', config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    SequenceFixtures::migrate();
});

it('answers 200 with the readiness report of a ready installation without reaching the endpoint', function (): void {
    Http::fake();

    $response = $this->getJson('/efatura/readiness', RouteFixtures::headers());

    $response->assertOk()
        ->assertJsonPath('ready', true)
        ->assertJsonMissingPath('data')
        ->assertJsonFragment(['code' => 'readiness.configured'])
        ->assertJsonFragment(['check' => 'endpoint', 'status' => 'skipped']);
    expect($response->getContent())->toContain('"context":{}')->not->toContain('"context":[]');
    Http::assertNothingSent();
});

it('answers 503 when a check blocks the installation', function (): void {
    SequenceFixtures::rollback();

    $this->getJson('/efatura/readiness', RouteFixtures::headers())
        ->assertServiceUnavailable()
        ->assertJsonPath('ready', false)
        ->assertJsonFragment(['code' => 'readiness.sequences_table_missing']);
});

it('reaches the configured endpoint only when remote readiness is enabled', function (): void {
    RouteFixtures::enable(['remote_readiness' => true]);
    P::configure('pem-encrypted', config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    Http::fake(['https://localhost:3443/v1/*' => Http::response('', 404, ['Date' => 'Fri, 02 Oct 2026 13:00:00 GMT'])]);

    $this->getJson('/efatura/readiness', RouteFixtures::headers())->assertOk()->assertJsonFragment(['check' => 'endpoint', 'code' => 'readiness.endpoint_reachable']);

    Http::assertSentCount(1);
});

it('never returns secrets or private key locations', function (): void {
    $body = $this->getJson('/efatura/readiness', RouteFixtures::headers())->getContent();

    expect($body)->not->toContain(str_repeat('k', 64))
        ->not->toContain(CertificateFixtures::PASSPHRASE)
        ->not->toContain('signer.key')
        ->not->toContain(CertificateFixtures::DISK);
});
