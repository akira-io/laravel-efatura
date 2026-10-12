<?php

declare(strict_types=1);

use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\SignatureException;
use Akira\Efatura\Signing\SignatureCertificate;
use Akira\Efatura\Signing\SignatureDigests;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;

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

it('canonicalizes every element of a document as the native subtree canonicalization does', function (string $xml): void {
    $document = new DOMDocument;
    $document->loadXML($xml);

    foreach ($document->getElementsByTagName('*') as $element) {
        expect(SignatureDigests::canonical($element))->toBe((string) $element->C14N(false, false));
    }
})->with([
    'namespaces and inherited xml attributes' => ['<r xmlns="urn:a" xmlns:p="urn:p" xml:lang="pt"><p:x xmlns:q="urn:q" a="1&amp;&lt;&quot;">'
        . '<q:y xml:space="preserve"> t&#xD; <![CDATA[<c>]]><!--c--><?pi x?></q:y><z xmlns=""/><p:w xmlns:p="urn:p2"/></p:x>é ü</r>'],
    'the nearest xml attribute wins' => ['<o xml:lang="en" xml:space="default"><m xml:lang="fr"><n a="&#x9;&#xA;" b="&quot;\'"/></m></o>'],
    'a latin-1 document'             => ['<?xml version="1.0" encoding="ISO-8859-1"?>' . "<r><a b='\xE9'>\xE9</a></r>"],
    'a signed document'              => fn (): string => S::sign(S::unsigned())->xml,
    'a detached signed event'        => fn (): string => S::sign(S::unsigned('FDC'), SignatureProfile::InternallyDetached)->xml,
]);

it('canonicalizes a whole document natively', function (): void {
    $document = new DOMDocument;
    $document->loadXML('<?pi x?><r b="2" a="1"/>');

    expect(SignatureDigests::canonical($document))->toBe("<?pi x?>\n<r a=\"1\" b=\"2\"></r>");
});

it('refuses to canonicalize an element its own serialization cannot reproduce', function (): void {
    $document = new DOMDocument;
    $document->loadXML('<r><a/></r>');

    $element = $document->getElementsByTagName('a')->item(0) ?? new DOMElement('missing');
    $element->appendChild($document->createComment('x--y'));

    expect(fn (): string => SignatureDigests::canonical($element))->toThrow(SignatureException::class, 'signature.canonicalization_failed');
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
