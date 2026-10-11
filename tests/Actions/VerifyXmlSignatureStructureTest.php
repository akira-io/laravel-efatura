<?php

declare(strict_types=1);

use Akira\Efatura\Actions\VerifyXmlSignatureAction;
use Akira\Efatura\Enums\SignatureFailure;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Signing\SignatureDigests;
use Akira\Efatura\Signing\SignatureVerification;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;
use Akira\Efatura\Tests\Support\SignatureEdits as E;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;

function verifyStructure(string $xml): SignatureVerification
{
    return resolve(VerifyXmlSignatureAction::class)->handle($xml);
}

function fiscalElement(DOMDocument $document, string $name, string $text = ''): DOMElement
{
    return $document->createElementNS(Fiscal::XML_NAMESPACE, $name, $text);
}

it('refuses content placed beside the signed document', function (SignatureProfile $profile, Closure $edit): void {
    $edited = E::edit(S::sign(S::unsigned(), $profile)->xml, $edit);

    expect(verifyStructure($edited)->failures)->toBe([SignatureFailure::Placement])
        ->and(verifyStructure($edited)->profile)->toBeNull();
})->with([
    'a second document after the signed one' => [SignatureProfile::InternallyDetached, function (DOMDocument $document, DOMXPath $xpath): void {
        $document->documentElement?->appendChild(E::element($xpath, '/internally-detached/e:Dfe')->cloneNode(true));
    }],
    'the document before the signature' => [SignatureProfile::InternallyDetached, function (DOMDocument $document, DOMXPath $xpath): void {
        $document->documentElement?->insertBefore(E::element($xpath, '/internally-detached/e:Dfe'), E::element($xpath, '//ds:Signature'));
    }],
    'a junk sibling of the detached document' => [SignatureProfile::InternallyDetached, function (DOMDocument $document): void {
        $document->documentElement?->appendChild($document->createElement('Junk'));
    }],
    'a junk element after an enveloped signature' => [SignatureProfile::Enveloped, function (DOMDocument $document): void {
        $document->documentElement?->appendChild(fiscalElement($document, 'Junk'));
    }],
    'a detached document in a foreign namespace' => [SignatureProfile::InternallyDetached, function (DOMDocument $document, DOMXPath $xpath): void {
        $signed  = E::element($xpath, '/internally-detached/e:Dfe');
        $foreign = $document->createElementNS('urn:forged', 'Dfe');
        $foreign->setAttribute('Id', $signed->getAttribute('Id'));
        $document->documentElement?->replaceChild($foreign, $signed);
    }],
]);

it('refuses a signature whose xmldsig or xades structure is not exactly the one the signer writes', function (Closure $edit): void {
    $edited = E::edit(S::sign(S::unsigned())->xml, $edit, resign: true);

    expect(verifyStructure($edited)->failures)->toBe([SignatureFailure::StructureUnexpected])
        ->and(verifyStructure($edited)->profile)->toBe(SignatureProfile::Enveloped)
        ->and(verifyStructure($edited)->valid)->toBeFalse();
})->with([
    'an extra object holding a payable amount' => [function (DOMDocument $document, DOMXPath $xpath): void {
        $object = $document->createElementNS(Fiscal::XMLDSIG_NAMESPACE, 'ds:Object');
        $object->appendChild(fiscalElement($document, 'PayableAmount', '1'));
        E::element($xpath, '//ds:Signature')->appendChild($object);
    }],
    'two signed infos' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:Signature')->insertBefore(E::element($xpath, '//ds:SignedInfo')->cloneNode(true), E::element($xpath, '//ds:SignatureValue'));
    }],
    'two signed properties' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//xades:QualifyingProperties')->appendChild(E::element($xpath, '//xades:SignedProperties')->cloneNode(true));
    }],
    'two digest values in a reference' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:Reference[2]')->appendChild(E::element($xpath, '//ds:Reference[2]/ds:DigestValue')->cloneNode(true));
    }],
    'no object'            => [fn (DOMDocument $document, DOMXPath $xpath): DOMNode => E::element($xpath, '//ds:Signature')->removeChild(E::element($xpath, '//ds:Object'))],
    'no key info'          => [fn (DOMDocument $document, DOMXPath $xpath): DOMNode => E::element($xpath, '//ds:Signature')->removeChild(E::element($xpath, '//ds:KeyInfo'))],
    'a second certificate' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:X509Data')->appendChild(E::element($xpath, '//ds:X509Certificate')->cloneNode(true));
    }],
    'unsigned properties' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//xades:QualifyingProperties')->appendChild($document->createElementNS(Fiscal::XADES_NAMESPACE, 'xades:UnsignedProperties'));
    }],
    'a transform with content' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:Reference[1]/ds:Transforms/ds:Transform')->appendChild(fiscalElement($document, 'Parameter'));
    }],
    'a reference without a digest method' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:Reference[2]')->removeChild(E::element($xpath, '//ds:Reference[2]/ds:DigestMethod'));
    }],
    'signed info without a canonicalization method' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:SignedInfo')->removeChild(E::element($xpath, '//ds:CanonicalizationMethod'));
    }],
    'a signature value before signed info' => [function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:Signature')->insertBefore(E::element($xpath, '//ds:SignatureValue'), E::element($xpath, '//ds:SignedInfo'));
    }],
]);

it('allows each reference only the transforms of its own target', function (SignatureProfile $profile, Closure $edit): void {
    $edited = E::edit(S::sign(S::unsigned(), $profile)->xml, $edit, resign: true);

    expect(verifyStructure($edited)->failures)->toBe([SignatureFailure::AlgorithmUnsupported]);
})->with([
    'an enveloped transform on the signed properties' => [
        SignatureProfile::Enveloped,
        E::appendTransform('//ds:Reference[2]', Fiscal::ENVELOPED_SIGNATURE_TRANSFORM),
    ],
    'an enveloped transform on a detached document' => [
        SignatureProfile::InternallyDetached,
        E::appendTransform('//ds:Reference[1]', Fiscal::ENVELOPED_SIGNATURE_TRANSFORM),
    ],
    'canonicalization before the enveloped transform' => [SignatureProfile::Enveloped, function (DOMDocument $document, DOMXPath $xpath): void {
        $transforms = E::element($xpath, '//ds:Reference[1]/ds:Transforms');
        $transforms->insertBefore(E::element($xpath, '//ds:Reference[2]/ds:Transforms/ds:Transform')->cloneNode(true), $transforms->firstChild);
    }],
    'an enveloped document without the enveloped transform' => [SignatureProfile::Enveloped, function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:Reference[1]')->removeChild(E::element($xpath, '//ds:Reference[1]/ds:Transforms'));
    }],
    'the enveloped transform twice'                   => [SignatureProfile::Enveloped, E::appendTransform('//ds:Reference[1]', Fiscal::ENVELOPED_SIGNATURE_TRANSFORM)],
    'canonicalization twice on the signed properties' => [SignatureProfile::Enveloped, E::appendTransform('//ds:Reference[2]', Fiscal::C14N_ALGORITHM)],
]);

it('refuses a data reference that declares a type', function (): void {
    $edited = E::edit(S::sign(S::unsigned())->xml, E::setAttribute('//ds:Reference[1]', 'Type', 'http://www.w3.org/2000/09/xmldsig#Object'), resign: true);

    expect(verifyStructure($edited)->failures)->toBe([SignatureFailure::ReferenceUnexpected]);
});

it('accepts what another conforming signer may write differently', function (SignatureProfile $profile, Closure $edit, bool $resign): void {
    $edited = E::edit(S::sign(S::unsigned(), $profile)->xml, $edit, resign: $resign);

    expect(verifyStructure($edited)->failures)->toBe([])
        ->and(verifyStructure($edited)->valid)->toBeTrue();
})->with([
    'a certificate wrapped at 64 columns' => [SignatureProfile::Enveloped, function (DOMDocument $document, DOMXPath $xpath): void {
        $certificate              = E::element($xpath, '//ds:X509Certificate');
        $certificate->textContent = "\n" . chunk_split($certificate->textContent, 64, "\n");
    }, false],
    'a certificate wrapped at 76 columns with carriage returns' => [SignatureProfile::InternallyDetached, function (DOMDocument $document, DOMXPath $xpath): void {
        $certificate              = E::element($xpath, '//ds:X509Certificate');
        $certificate->textContent = chunk_split($certificate->textContent, 76, "\r\n");
    }, false],
    'a padded reference digest' => [SignatureProfile::Enveloped, function (DOMDocument $document, DOMXPath $xpath): void {
        $digest              = E::element($xpath, '//ds:Reference[1]/ds:DigestValue');
        $digest->textContent = "\n  " . $digest->textContent . "\n";
    }, true],
    'a padded certificate digest' => [SignatureProfile::Enveloped, function (DOMDocument $document, DOMXPath $xpath): void {
        $digest              = E::element($xpath, '//xades:CertDigest/ds:DigestValue');
        $digest->textContent = ' ' . $digest->textContent . "\t";

        E::element($xpath, '//ds:Reference[2]/ds:DigestValue')->textContent = SignatureDigests::node(E::element($xpath, '//xades:SignedProperties'));
    }, true],
    'the references in reverse order' => [SignatureProfile::Enveloped, function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:SignedInfo')->insertBefore(E::element($xpath, '//ds:Reference[2]'), E::element($xpath, '//ds:Reference[1]'));
    }, true],
    'canonicalization on a detached document' => [SignatureProfile::InternallyDetached, E::appendTransform('//ds:Reference[1]', Fiscal::C14N_ALGORITHM), true],
    'no transform on the signed properties'   => [SignatureProfile::Enveloped, function (DOMDocument $document, DOMXPath $xpath): void {
        E::element($xpath, '//ds:Reference[2]')->removeChild(E::element($xpath, '//ds:Reference[2]/ds:Transforms'));
    }, true],
]);

it('verifies a document the caller already parsed without reading it again', function (): void {
    $signed   = S::sign(S::unsigned())->xml;
    $document = S::document($signed);

    expect(resolve(VerifyXmlSignatureAction::class)->handle($document))->toEqual(verifyStructure($signed))
        ->and($document->saveXML())->toBe(S::document($signed)->saveXML());
});

it('reports a certificate carrying trailing der as unreadable instead of failing', function (string $suffix): void {
    $certificate = base64_encode(C::signer()->der() . $suffix);
    $edited      = E::edit(S::sign(S::unsigned())->xml, E::replaceCertificate($certificate));

    expect(verifyStructure($edited)->failures)->toBe([SignatureFailure::CertificateInvalid])
        ->and(verifyStructure($edited)->certificateSubject)->toBeNull();
})->with([
    'an element with nine length octets' => ["\x30\x89" . str_repeat("\xff", 9)],
    'a single zero byte'                 => ["\x00"],
]);
