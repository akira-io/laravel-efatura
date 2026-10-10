<?php

declare(strict_types=1);

use Akira\Efatura\Signing\OpenSslErrors;

it('drains the openssl error queue as numeric codes', function (): void {
    OpenSslErrors::drain();
    openssl_pkey_get_private('not a key');

    expect(OpenSslErrors::drain())->not->toBeEmpty()->each->toBeInt()
        ->and(openssl_error_string())->toBeFalse();
});

it('classifies a pkcs12 failure by its openssl reason codes', function (array $codes, string $errorCode): void {
    expect(OpenSslErrors::pkcs12Failure($codes))->toBe($errorCode);
})->with([
    'mac verify failure'    => [[0x11800071], 'certificate.passphrase_invalid'],
    'legacy algorithm'      => [[0x0308010C, 0x11800073], 'certificate.pkcs12_unsupported'],
    'asn1 decoding failure' => [[0x0680008E], 'certificate.invalid'],
    'empty queue'           => [[], 'certificate.invalid'],
]);
