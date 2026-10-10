<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Contracts\XmlSigner;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Exceptions\SignatureException;
use Akira\Efatura\Signing\SignedXml;
use Akira\Efatura\Signing\SigningCredentials;
use Akira\Efatura\Signing\XadesBesXmlSigner;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Akira\Efatura\Tests\Support\SignatureVerifier as V;

it('is the xml signer the container resolves', function (): void {
    expect(resolve(XmlSigner::class))->toBeInstanceOf(XadesBesXmlSigner::class);
});

it('signs documents and events so an independent verifier accepts both references', function (string $kind, SignatureProfile $profile): void {
    $unsigned = S::unsigned($kind);
    $signed   = S::sign($unsigned, $profile);
    $xpath    = V::xpath(S::document($signed->xml));
    $root     = S::document($unsigned);

    V::verify($signed->xml);

    expect($signed->id)->toBe($root->documentElement?->getAttribute('Id'))
        ->and($signed->profile)->toBe($profile)
        ->and($signed->signingTime->format(Fiscal::DATE_TIME_FORMAT))->toBe(S::LOCAL_SIGNING_TIME)
        ->and($signed->certificateDigest)->toBe(base64_encode(hash('sha256', C::signer()->der(), true)))
        ->and(V::text($xpath, '//xades:CertDigest/ds:DigestValue'))->toBe($signed->certificateDigest)
        ->and(V::text($xpath, '//xades:IssuerSerial/ds:X509IssuerName'))->toBe(C::CA_NAME)->toBe($signed->issuerName)
        ->and(V::text($xpath, '//xades:IssuerSerial/ds:X509SerialNumber'))->toBe('1715004')->toBe($signed->serialNumber)
        ->and(V::text($xpath, '//ds:X509Certificate'))->toBe(base64_encode(C::signer()->der()))
        ->and(fn () => resolve(SchemaValidator::class)->validate($signed->xml, $profile))->not->toThrow(SchemaValidationException::class);
})->with(S::documentsAndProfiles());

it('writes the signature structure of the official examples', function (string $kind, SignatureProfile $profile): void {
    $signed = S::sign(S::unsigned($kind), $profile);
    $xpath  = V::xpath(S::document($signed->xml));
    $names  = fn (string $query): array => array_map(fn (DOMNode $node): string => $node->nodeName, iterator_to_array($xpath->query($query) ?: []));
    $value  = fn (string $query): string => V::text($xpath, $query);

    expect($names('//ds:Signature/*'))->toBe(['ds:SignedInfo', 'ds:SignatureValue', 'ds:KeyInfo', 'ds:Object'])
        ->and($names('//ds:SignedInfo/*'))->toBe(['ds:CanonicalizationMethod', 'ds:SignatureMethod', 'ds:Reference', 'ds:Reference'])
        ->and($names('//ds:Reference[1]/*'))->toBe($profile === SignatureProfile::Enveloped
            ? ['ds:Transforms', 'ds:DigestMethod', 'ds:DigestValue'] : ['ds:DigestMethod', 'ds:DigestValue'])
        ->and($names('//ds:Reference[2]/*'))->toBe(['ds:Transforms', 'ds:DigestMethod', 'ds:DigestValue'])
        ->and($names('//xades:SignedSignatureProperties/*'))->toBe(['xades:SigningTime', 'xades:SigningCertificate'])
        ->and($names('//xades:SignedProperties/*'))->toBe(['xades:SignedSignatureProperties', 'xades:SignedDataObjectProperties'])
        ->and($value('//ds:Signature/@Id'))->toBe('EmitterPartySignatureId')
        ->and($value('//ds:CanonicalizationMethod/@Algorithm'))->toBe('http://www.w3.org/TR/2001/REC-xml-c14n-20010315')
        ->and($value('//ds:SignatureMethod/@Algorithm'))->toBe('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256')
        ->and($value('//ds:Reference[1]/@Id'))->toBe('DataReferenceId')
        ->and($value('//ds:Reference[1]/@URI'))->toBe('#' . $signed->id)
        ->and($value('//ds:Reference[1]/ds:DigestMethod/@Algorithm'))->toBe('http://www.w3.org/2001/04/xmlenc#sha256')
        ->and($value('//ds:Reference[2]/@URI'))->toBe('#SignedPropertiesId')
        ->and($value('//ds:Reference[2]/@Type'))->toBe('http://uri.etsi.org/01903#SignedProperties')
        ->and($value('//ds:Reference[2]/ds:Transforms/ds:Transform/@Algorithm'))->toBe('http://www.w3.org/TR/2001/REC-xml-c14n-20010315')
        ->and($value('//xades:QualifyingProperties/@Target'))->toBe('#EmitterPartySignatureId')
        ->and($value('//xades:SignedProperties/@Id'))->toBe('SignedPropertiesId')
        ->and($value('//xades:SigningTime'))->toBe('2026-10-02T23:30:00')
        ->and($value('//xades:CertDigest/ds:DigestMethod/@Algorithm'))->toBe('http://www.w3.org/2001/04/xmlenc#sha256')
        ->and($value('//xades:DataObjectFormat/@ObjectReference'))->toBe('#DataReferenceId')
        ->and($value('//xades:DataObjectFormat/xades:MimeType'))->toBe('text/xml')
        ->and($xpath->query('//xades:SignatureProductionPlace')?->length)->toBe(0)
        ->and($xpath->query('//text()[normalize-space(.) = ""]')?->length)->toBe(0)
        ->and($value('//ds:SignatureValue'))->toMatch('/\A[A-Za-z0-9+\/]+={0,2}\z/')
        ->and(preg_match_all('/xmlns:[a-z]+=/', $signed->xml, $declarations))->toBe(2)
        ->and($declarations[0])->toBe(['xmlns:ds=', 'xmlns:xades=']);

    if ($profile === SignatureProfile::Enveloped) {
        expect($value('//ds:Reference[1]/ds:Transforms/ds:Transform/@Algorithm'))->toBe('http://www.w3.org/2000/09/xmldsig#enveloped-signature')
            ->and($names('/*/*[last()]'))->toBe(['ds:Signature'])
            ->and(S::document($signed->xml)->documentElement?->namespaceURI)->toBe(Fiscal::XML_NAMESPACE);

        return;
    }

    expect($names('/*'))->toBe(['internally-detached'])
        ->and(S::document($signed->xml)->documentElement?->namespaceURI)->toBeNull()
        ->and($names('/*/*'))->toBe(['ds:Signature', $kind === 'FDC' ? 'Event' : 'Dfe']);
})->with(S::documentsAndProfiles());

it('produces the same bytes for the same document, clock and key', function (SignatureProfile $profile): void {
    $unsigned = S::unsigned();

    expect(S::sign($unsigned, $profile)->xml)->toBe(S::sign($unsigned, $profile)->xml)
        ->and(S::sign($unsigned, $profile)->xml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
})->with(SignatureProfile::cases());

it('declares the utf-8 encoding even when the input does not', function (): void {
    $unsigned = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '<?xml version="1.0"?>', S::unsigned());

    expect(S::sign($unsigned)->xml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>');
});

it('refuses xml it must not sign without echoing it', function (Closure $xml, string $errorCode): void {
    $unsigned = $xml(S::unsigned());

    expect(fn (): SignedXml => S::sign($unsigned))->toThrow(function (EfaturaException $exception) use ($errorCode): void {
        expect($exception->errorCode)->toBe($errorCode)
            ->and($exception->getMessage())->toBe($errorCode)
            ->and($exception->context)->not->toHaveKey('xml');
    });
})->with([
    'foreign root'         => [fn (string $xml): string => '<Foo xmlns="urn:cv:efatura:xsd:v1.0" Id="x"/>', 'signature.unsupported_root'],
    'unqualified dfe'      => [fn (string $xml): string => '<Dfe Id="x"/>', 'signature.unsupported_root'],
    'dfe without id'       => [fn (string $xml): string => preg_replace('/ Id="[^"]+"/', '', $xml, 1), 'signature.missing_id'],
    'dfe with an event id' => [fn (string $xml): string => preg_replace('/ Id="[^"]+"/', ' Id="CV3261002120000123456789"', $xml, 1), 'signature.missing_id'],
    'event with an iud'    => [fn (string $xml): string => '<Event xmlns="urn:cv:efatura:xsd:v1.0" Id="CV3261002100200300000010100000000112345678902"/>', 'signature.missing_id'],
    'already signed'       => [fn (string $xml): string => S::sign($xml)->xml, 'signature.already_signed'],
    'document type'        => [fn (string $xml): string => '<!DOCTYPE Dfe [<!ENTITY a "b">]><Dfe>&a;</Dfe>', 'xml.doctype_forbidden'],
    'malformed'            => [fn (string $xml): string => '<Dfe>', 'xml.malformed'],
]);

it('refuses credentials whose key cannot be verified with their certificate', function (): void {
    $credentials = C::credentials();
    $mismatched  = new SigningCredentials(
        $credentials->certificate,
        C::key('other'),
        $credentials->certificateDer,
        $credentials->issuerName,
        $credentials->serialNumber,
        $credentials->validFrom,
        $credentials->validTo,
    );

    expect(fn (): SignedXml => S::sign(S::unsigned(), credentials: $mismatched))->toThrow(SignatureException::class, 'signature.failed')
        ->and(openssl_error_string())->toBeFalse();
});
