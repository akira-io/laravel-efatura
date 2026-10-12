<?php

declare(strict_types=1);

use Akira\Efatura\Actions\VerifyXmlSignatureAction;
use Akira\Efatura\Enums\SignatureFailure;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Signing\SignatureVerification;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;
use Akira\Efatura\Tests\Support\SignatureEdits as E;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Akira\Efatura\Tests\Support\SignatureVerifier as V;

function verifySignature(string $xml): SignatureVerification
{
    return resolve(VerifyXmlSignatureAction::class)->handle($xml);
}

it('accepts what the package signer writes and reads the certificate it carries', function (string $kind, SignatureProfile $profile): void {
    $signed = S::sign(S::unsigned($kind), $profile);

    $verification = verifySignature($signed->xml);

    expect(V::isValid($signed->xml))->toBeTrue()
        ->and($verification->valid)->toBeTrue()
        ->and($verification->failures)->toBe([])
        ->and($verification->profile)->toBe($profile)
        ->and($verification->certificateSubject)->toBe('CN=e-Fatura Test Signer,O=e-Fatura Test,C=CV')
        ->and($verification->certificateSerialNumber)->toBe('1715004')->toBe($signed->serialNumber)
        ->and($verification->certificateDigest)->toBe($signed->certificateDigest);
})->with(S::documentsAndProfiles());

it('reports a change to the signed content, its properties or the signature', function (SignatureProfile $profile, Closure $tamper, array $failures): void {
    $tampered = $tamper(S::sign(S::unsigned(), $profile)->xml);

    $verification = verifySignature($tampered);

    expect($verification->valid)->toBeFalse()
        ->and($verification->failures)->toBe($failures)
        ->and($verification->profile)->toBe($profile)
        ->and(V::isValid($tampered))->toBeFalse();
})->with(SignatureProfile::cases())->with([
    'payable amount'  => [fn (string $xml): string => str_replace('<PayableAmount>115</PayableAmount>', '<PayableAmount>116</PayableAmount>', $xml), [SignatureFailure::DigestMismatch]],
    'signing time'    => [fn (string $xml): string => str_replace(S::LOCAL_SIGNING_TIME, '2026-10-02T23:30:01', $xml), [SignatureFailure::DigestMismatch]],
    'signature value' => [fn (string $xml): string => S::flipBase64($xml, '//ds:SignatureValue'), [SignatureFailure::ValueInvalid]],
    'certificate'     => [
        fn (string $xml): string => E::edit($xml, E::replaceCertificate(base64_encode(C::issue(key: C::key('other'))->der()))),
        [SignatureFailure::CertificateMismatch, SignatureFailure::ValueInvalid],
    ],
]);

it('reports a certificate it cannot read or whose key is not rsa', function (string $certificate, array $failures, bool $presented): void {
    $verification = verifySignature(E::edit(S::sign(S::unsigned())->xml, E::replaceCertificate($certificate)));

    expect($verification->failures)->toBe($failures)
        ->and($verification->valid)->toBeFalse()
        ->and($verification->certificateSubject !== null)->toBe($presented);
})->with([
    'not base64'        => ['%%%', [SignatureFailure::CertificateInvalid], false],
    'not a certificate' => [base64_encode('not a certificate'), [SignatureFailure::CertificateInvalid], false],
    'an ec key'         => [
        fn (): string => base64_encode(C::issue(key: C::ecKey())->der()),
        [SignatureFailure::KeyUnsupported, SignatureFailure::CertificateMismatch],
        true,
    ],
]);

it('reports a document without a signature or with more than one', function (): void {
    $signed = S::sign(S::unsigned())->xml;
    $twice  = E::edit($signed, function (DOMDocument $document, DOMXPath $xpath): void {
        $document->documentElement?->appendChild(E::element($xpath, '//ds:Signature')->cloneNode(true));
    });

    expect(verifySignature(S::unsigned())->failures)->toBe([SignatureFailure::Missing])
        ->and(verifySignature(S::unsigned())->profile)->toBeNull()
        ->and(verifySignature($twice)->failures)->toBe([SignatureFailure::Multiple]);
});

it('reports a signature placed where the signer never writes it', function (SignatureProfile $profile, Closure $move): void {
    $moved = E::edit(S::sign(S::unsigned(), $profile)->xml, $move);

    expect(verifySignature($moved)->failures)->toBe([SignatureFailure::Placement])
        ->and(verifySignature($moved)->profile)->toBeNull();
})->with([
    'inside a child of the document' => [SignatureProfile::Enveloped, function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '/e:Dfe/*[1]')->appendChild(E::element($xpath, '//ds:Signature'));
    }],
    'under a foreign root' => [SignatureProfile::InternallyDetached, function (DOMDocument $document, DOMXPath $xpath): void {
        $document->documentElement?->appendChild($document->createElement('wrapper'))->appendChild(E::element($xpath, '//ds:Signature'));
        $document->replaceChild(E::element($xpath, '//wrapper'), E::element($xpath, '/internally-detached'));
    }],
    'beside an element that is not a document' => [SignatureProfile::InternallyDetached, function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '/internally-detached')->replaceChild($document->createElement('Other'), E::element($xpath, '/internally-detached/e:Dfe'));
    }],
    'before nothing' => [SignatureProfile::InternallyDetached, function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '/internally-detached')->removeChild(E::element($xpath, '/internally-detached/e:Dfe'));
    }],
]);

it('reports references other than the two the signer writes', function (Closure $edit): void {
    $edited = E::edit(S::sign(S::unsigned())->xml, $edit, resign: true);

    expect(verifySignature($edited)->failures)->toBe([SignatureFailure::ReferenceUnexpected]);
})->with([
    'a data reference to another element'      => [E::setAttribute('//ds:Reference[1]', 'URI', '#SignedPropertiesId')],
    'properties referenced without their type' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:Reference[2]')->removeAttribute('Type');
    }],
    'a third reference' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:SignedInfo')->appendChild(E::element($xpath, '//ds:Reference[2]')->cloneNode(true));
    }],
    'an id used twice' => [function (DOMDocument $document, DOMXPath $xpath): void {
        $decoy = $document->createElementNS(Fiscal::XML_NAMESPACE, 'Decoy');
        $decoy->setAttribute('Id', (string) $document->documentElement?->getAttribute('Id'));
        E::element($xpath, '/e:Dfe/e:Invoice')->appendChild($decoy);
    }],
]);

it('accepts the canonicalization transform after the enveloped one', function (): void {
    $edited = E::edit(S::sign(S::unsigned())->xml, E::appendTransform('//ds:Reference[1]', Fiscal::C14N_ALGORITHM), resign: true);

    expect(verifySignature($edited)->valid)->toBeTrue()
        ->and(V::isValid($edited))->toBeTrue();
});

it('refuses every algorithm the signer does not write', function (Closure $edit): void {
    $edited = E::edit(S::sign(S::unsigned())->xml, $edit, resign: true);

    expect(verifySignature($edited)->failures)->toBe([SignatureFailure::AlgorithmUnsupported]);
})->with([
    'exclusive canonicalization' => [E::setAlgorithm('//ds:CanonicalizationMethod', 'http://www.w3.org/2001/10/xml-exc-c14n#')],
    'rsa with sha-1'             => [E::setAlgorithm('//ds:SignatureMethod', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1')],
    'a sha-1 digest'             => [E::setAlgorithm('//ds:Reference[2]/ds:DigestMethod', 'http://www.w3.org/2000/09/xmldsig#sha1')],
    'an xslt transform'          => [E::appendTransform('//ds:Reference[2]', 'http://www.w3.org/TR/1999/REC-xslt-19991116')],
]);

it('reports a signing certificate digest that is not the sha-256 of the key info certificate', function (): void {
    $edited = E::edit(S::sign(S::unsigned())->xml, E::setAlgorithm('//xades:CertDigest/ds:DigestMethod', 'http://www.w3.org/2000/09/xmldsig#sha1'));

    expect(verifySignature($edited)->failures)->toBe([SignatureFailure::DigestMismatch, SignatureFailure::CertificateMismatch]);
});

it('lets the hardened parser refuse what is not well formed xml', function (string $xml, string $code): void {
    expect(fn (): SignatureVerification => verifySignature($xml))->toThrow(SchemaValidationException::class, $code);
})->with([
    'malformed' => ['<Dfe><unclosed></Dfe>', 'xml.malformed'],
    'xxe'       => ['<?xml version="1.0"?><!DOCTYPE Dfe [<!ENTITY x SYSTEM "file:///etc/passwd">]><Dfe>&x;</Dfe>', 'xml.doctype_forbidden'],
]);

it('names each failure with a stable package code', function (SignatureFailure $failure): void {
    expect($failure->code())->toBe('signature.' . $failure->value)->toMatch('/\Asignature\.[a-z_]+\z/');
})->with(SignatureFailure::cases());
