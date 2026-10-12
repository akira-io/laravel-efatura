<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

it('writes a self-signed test certificate and its key on the certificates disk', function (): void {
    $disk = Storage::fake('certificates');

    $this->artisan('playground:certificate')->assertSuccessful();

    $certificate = openssl_x509_parse($disk->get('playground.crt'));

    expect($certificate['subject']['CN'])->toBe('e-Fatura Playground Test Signer (not ICP-CV)')
        ->and($certificate['issuer'])->toBe($certificate['subject'])
        ->and(openssl_x509_check_private_key($disk->get('playground.crt'), $disk->get('playground.key')))->toBeTrue();
});

it('keeps an existing certificate unless asked to replace it', function (): void {
    $disk = Storage::fake('certificates');
    $this->artisan('playground:certificate')->assertSuccessful();
    $first = $disk->get('playground.crt');

    $this->artisan('playground:certificate')->assertSuccessful();
    $kept = $disk->get('playground.crt');

    $this->artisan('playground:certificate', ['--force' => true])->assertSuccessful();

    expect($kept)->toBe($first)
        ->and($disk->get('playground.crt'))->not->toBe($first);
});
