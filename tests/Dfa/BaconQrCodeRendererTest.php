<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\QrCodeRenderer;
use Akira\Efatura\Dfa\BaconQrCodeRenderer;
use Akira\Efatura\Dfa\LibraryRequirement;
use Akira\Efatura\Exceptions\DfaException;
use Akira\Efatura\Tests\Support\DfaFixtures as D;

it('draws the QR code as a standalone SVG through the bound renderer', function (): void {
    $renderer = resolve(QrCodeRenderer::class);
    $svg      = $renderer->render('https://pe.efatura.cv/dfe/view/' . D::iud());

    expect($renderer)->toBeInstanceOf(BaconQrCodeRenderer::class)
        ->and($svg)->toStartWith('<?xml')
        ->toContain('<svg')
        ->not->toContain('<script')
        ->not->toContain('<image')
        ->and(simplexml_load_string($svg))->not->toBeFalse();
});

it('draws the same QR code for the same payload and another one for another payload', function (): void {
    $renderer = resolve(QrCodeRenderer::class);
    $payload  = 'https://pe.efatura.cv/dfe/view/' . D::iud();

    expect($renderer->render($payload))->toBe($renderer->render($payload))
        ->not->toBe($renderer->render($payload . '0'));
});

it('fails with a stable code when the QR library is not installed', function (): void {
    $renderer = new BaconQrCodeRenderer(new LibraryRequirement('bacon/bacon-qr-code', 'Missing\BaconQrCode\Writer'));

    expect(fn (): string => $renderer->render('https://pe.efatura.cv/dfe/view/' . D::iud()))
        ->toThrow(function (DfaException $exception): void {
            expect($exception->errorCode)->toBe('dfa.renderer_unavailable')
                ->and($exception->context)->toBe(['package' => 'bacon/bacon-qr-code']);
        });
});
