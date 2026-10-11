<?php

declare(strict_types=1);

use Akira\Efatura\Signing\SignatureCertificate;
use Akira\Efatura\Signing\SignatureDigests;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;

it('digests bytes as the base64 of their sha-256', function (): void {
    expect(SignatureDigests::of('abc'))->toBe('ungWv48Bz+pBQUDeXa4iI7ADYaOWF3qctBD/YfIAFa0=');
});

it('canonicalizes a node with inclusive c14n 1.0 without comments and digests the result', function (): void {
    $document = new DOMDocument;
    $document->loadXML('<a xmlns="urn:x"><!-- note --><b c="1"  d="2"/></a>');

    $node = $document->getElementsByTagName('b')->item(0) ?? new DOMElement('missing');

    expect(SignatureDigests::canonical($node))->toBe('<b xmlns="urn:x" c="1" d="2"></b>')
        ->and(SignatureDigests::node($node))->toBe(SignatureDigests::of('<b xmlns="urn:x" c="1" d="2"></b>'));
});

it('reads a hexadecimal serial number as a decimal one', function (string $hex, string $decimal): void {
    expect(SignatureDigests::serialNumber($hex))->toBe($decimal);
})->with([
    'small'          => ['1A2B3C', '1715004'],
    'twenty octets'  => ['7FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF', '730750818665451459101842416358141509827966271487'],
    'leading zeroes' => ['00FF', '255'],
]);

it('gives the signer and the verifier the same certificate digest and serial number', function (): void {
    $credentials = C::credentials();
    $certificate = SignatureCertificate::fromBase64(base64_encode($credentials->certificateDer));

    expect($credentials->certificateDigest())->toBe(SignatureDigests::of($credentials->certificateDer))->toBe($certificate?->digest())
        ->and($credentials->serialNumber)->toBe($certificate?->serialNumber());
});
