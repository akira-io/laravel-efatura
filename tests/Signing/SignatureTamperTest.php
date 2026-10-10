<?php

declare(strict_types=1);

use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Akira\Efatura\Tests\Support\SignatureVerifier as V;

it('detects any change to the signed content, its properties or the signature', function (SignatureProfile $profile, Closure $tamper): void {
    $signed   = S::sign(S::unsigned(), $profile)->xml;
    $tampered = $tamper($signed);

    expect(V::isValid($signed))->toBeTrue()
        ->and($tampered)->not->toBe($signed)
        ->and(V::isValid($tampered))->toBeFalse();
})->with(SignatureProfile::cases())->with([
    'payable amount'  => [fn (string $xml): string => str_replace('<PayableAmount>115</PayableAmount>', '<PayableAmount>116</PayableAmount>', $xml)],
    'document id'     => [fn (string $xml): string => (string) preg_replace('/(<Dfe [^>]*Id="CV[0-9]+)2"/', '${1}3"', $xml, 1)],
    'signing time'    => [fn (string $xml): string => str_replace(S::LOCAL_SIGNING_TIME, '2026-10-02T23:30:01', $xml)],
    'certificate'     => [fn (string $xml): string => S::flipBase64($xml, '//ds:X509Certificate')],
    'signature value' => [fn (string $xml): string => S::flipBase64($xml, '//ds:SignatureValue')],
]);
