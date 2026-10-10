<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;
use Akira\Efatura\Tests\Support\SchemaFixtures;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Akira\Efatura\Tests\Support\SignatureShape;

it('writes the signature shape of every official example, apart from the known divergences', function (string $example, SignatureProfile $profile, string $kind): void {
    $official  = SignatureShape::of(SchemaFixtures::official($example));
    $generated = SignatureShape::of(S::sign(S::unsigned($kind), $profile)->xml);
    $place     = array_values(array_filter($official, fn (string $line): bool => str_contains($line, 'SignatureProductionPlace')));
    $expected  = SignatureShape::without($official, 'xades:SignatureProductionPlace');

    if ($example === '1 Invoice - EnvelopedSignature.xml') {
        $exclusive = array_search('    ds:Transform Algorithm=http://www.w3.org/2001/10/xml-exc-c14n', $expected, true);
        expect($exclusive)->toBeInt();
        $expected[$exclusive] = '    ds:Transform Algorithm=http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    }

    if ($profile === SignatureProfile::Enveloped && $example !== '1 Invoice - EnvelopedSignature.xml') {
        $dataReference = array_search('  ds:Reference Id=DataReferenceId URI=#ROOT', $expected, true);
        expect($expected[$dataReference + 1])->toBe('   ds:DigestMethod Algorithm=http://www.w3.org/2001/04/xmlenc#sha256');
        array_splice($expected, $dataReference + 1, 0, SignatureShape::ENVELOPED_TRANSFORM);
    }

    expect($place)->toHaveCount(1)
        ->and($generated)->toBe($expected);
})->with([
    ['1 Invoice - EnvelopedSignature.xml', SignatureProfile::Enveloped, 'FTE'],
    ['1 Invoice - InternallyDetachedSignature.xml', SignatureProfile::InternallyDetached, 'FTE'],
    ['2 InvoiceReceipt.xml', SignatureProfile::Enveloped, 'FTE'],
    ['3 SalesReceipt.xml', SignatureProfile::Enveloped, 'FTE'],
    ['4 Receipt.xml', SignatureProfile::Enveloped, 'FTE'],
    ['5 CreditNote.xml', SignatureProfile::Enveloped, 'FTE'],
    ['6 DebitNote.xml', SignatureProfile::Enveloped, 'FTE'],
    ['7 Transport.xml', SignatureProfile::Enveloped, 'FTE'],
    ['8 ReturnNote.xml', SignatureProfile::Enveloped, 'FTE'],
    ['9 RegistrationNote.xml', SignatureProfile::Enveloped, 'FTE'],
]);

it('points the event data reference at its own id where the official event example points at an iud', function (): void {
    $official  = SignatureShape::of(SchemaFixtures::official('99 Event.xml'));
    $generated = SignatureShape::of(S::sign(S::unsigned('FDC'))->xml);
    $reference = '  ds:Reference Id=DataReferenceId URI=#CV1200520123456789000112345678901112345678904';
    $position  = array_search($reference, $official, true);

    expect($position)->toBeInt()
        ->and(FiscalRules::isIud('CV1200520123456789000112345678901112345678904'))->toBeTrue();

    $expected            = SignatureShape::without($official, 'xades:SignatureProductionPlace');
    $expected[$position] = '  ds:Reference Id=DataReferenceId URI=#ROOT';
    array_splice($expected, $position + 1, 0, SignatureShape::ENVELOPED_TRANSFORM);

    expect($generated)->toBe($expected);
});

it('validates a signature with a twenty byte serial number except on the libxml releases that reject it', function (SignatureProfile $profile): void {
    $signed = S::sign(S::unsigned(), $profile, C::credentials(C::longSerialSigner()));

    try {
        resolve(SchemaValidator::class)->validate($signed->xml, $profile);
        $messages = [];
    } catch (SchemaValidationException $schemaValidationException) {
        $messages = array_column($schemaValidationException->violations, 'message');
    }

    expect($signed->serialNumber)->toHaveLength(46)
        ->and($messages)->toBe(LIBXML_VERSION >= 21000 ? [] : ["Element \x27{http://www.w3.org/2000/09/xmldsig#}X509SerialNumber\x27: \x27…\x27"]);
})->with(SignatureProfile::cases());
