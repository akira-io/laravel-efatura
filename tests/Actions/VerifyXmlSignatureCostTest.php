<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ValidateXmlDocumentAction;
use Akira\Efatura\Actions\VerifyXmlSignatureAction;
use Akira\Efatura\Enums\SignatureFailure;
use Akira\Efatura\Signing\SignatureDigests;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Akira\Efatura\Tests\Support\SignatureVerifier as V;
use Akira\Efatura\Xml\XmlComplexity;

function signedWithLines(int $lines): string
{
    $document = S::document(S::unsigned());
    $list     = $document->getElementsByTagName('Lines')->item(0);
    $line     = $list?->getElementsByTagName('Line')->item(0);
    for ($copy = 1; $copy < $lines; $copy++) {
        $list?->appendChild($line?->cloneNode(true) ?? new DOMElement('Line'));
    }

    return S::sign((string) $document->saveXML())->xml;
}

/**
 * @return array{float, bool}
 */
function fastestVerification(string $xml): array
{
    $timings = [];
    foreach (range(1, 5) as $ignored) {
        gc_collect_cycles();
        $started   = hrtime(true);
        $valid     = resolve(VerifyXmlSignatureAction::class)->handle($xml)->valid;
        $timings[] = (hrtime(true) - $started) / 1e9;
    }

    return [min($timings), $valid];
}

it('digests the enveloped content exactly as the node-set of the enveloped transform', function (string $xml): void {
    $document = S::document(S::sign($xml)->xml);
    $xpath    = V::xpath($document);
    $nodes    = ['query' => '(.//. | .//@* | .//namespace::*)[not(ancestor-or-self::ds:Signature)]', 'namespaces' => ['ds' => Fiscal::XMLDSIG_NAMESPACE]];

    expect(V::text($xpath, '//ds:Reference[1]/ds:DigestValue'))
        ->toBe(SignatureDigests::of((string) $document->documentElement?->C14N(false, false, $nodes)));
})->with(fn (): array => collect(glob(__DIR__ . '/../Fixtures/xml/{documents,events}/*.xml', GLOB_BRACE) ?: [])
    ->mapWithKeys(static fn (string $path): array => [basename(dirname($path)) . '/' . basename($path) => [(string) file_get_contents($path)]])
    ->all());

it('verifies a signed document of 1000 lines in time linear in its size', function (): void {
    [$small, $smallValid] = fastestVerification(signedWithLines(250));
    [$large, $largeValid] = fastestVerification(signedWithLines(1000));

    expect($smallValid)->toBeTrue()
        ->and($largeValid)->toBeTrue()
        ->and($large)->toBeLessThan(5.0)
        ->and($large / max($small, 0.02))->toBeLessThan(8.0);
});

it('verifies a document crafted to make canonicalization quadratic quickly', function (): void {
    $declarations = implode('', array_map(static fn (int $index): string => ' xmlns:n' . $index . '="urn:n' . $index . '"', range(1, XmlComplexity::MAX_NAMESPACES_IN_SCOPE - 3)));
    $crafted      = str_replace(
        ['<Dfe xmlns="urn:cv:efatura:xsd:v1.0"', '</Invoice>'],
        ['<Dfe xmlns="urn:cv:efatura:xsd:v1.0"' . $declarations, '<Filler>' . str_repeat('<a/>', 12_000) . '</Filler></Invoice>'],
        S::sign(S::unsigned())->xml,
    );

    $started      = hrtime(true);
    $verification = resolve(VerifyXmlSignatureAction::class)->handle($crafted);
    $validation   = resolve(ValidateXmlDocumentAction::class)->handle($crafted);
    $elapsed      = (hrtime(true) - $started) / 1e9;

    expect(strlen($crafted))->toBeGreaterThan(45_000)
        ->and($verification->failures)->toBe([SignatureFailure::DigestMismatch, SignatureFailure::ValueInvalid])
        ->and($validation->valid())->toBeFalse()
        ->and($elapsed)->toBeLessThan(5.0);
});
