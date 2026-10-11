<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Enums\SignatureFailure;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Signing\SignatureCertificate;
use Akira\Efatura\Signing\SignatureDigests;
use Akira\Efatura\Signing\SignatureVerification;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\SafeXmlParser;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final readonly class VerifyXmlSignatureAction
{
    private const string ENVELOPED_NODES = '(.//. | .//@* | .//namespace::*)[not(ancestor-or-self::ds:Signature)]';

    private const array TRANSFORMS = [Fiscal::ENVELOPED_SIGNATURE_TRANSFORM, Fiscal::C14N_ALGORITHM];

    public function __construct(private SafeXmlParser $parser) {}

    public function handle(string $xml): SignatureVerification
    {
        $document = $this->parser->parse($xml);
        $xpath    = new DOMXPath($document);
        $xpath->registerNamespace('ds', Fiscal::XMLDSIG_NAMESPACE);
        $xpath->registerNamespace('xades', Fiscal::XADES_NAMESPACE);

        $signatures = self::elements($xpath, '//ds:Signature');
        if (\count($signatures) !== 1) {
            return new SignatureVerification([$signatures === [] ? SignatureFailure::Missing : SignatureFailure::Multiple]);
        }

        $signature = $signatures[0];
        $placement = self::placement($document, $signature);
        if ($placement === null) {
            return new SignatureVerification([SignatureFailure::Placement]);
        }

        [$profile, $signed] = $placement;
        $properties         = self::elements($xpath, 'ds:Object/xades:QualifyingProperties/xades:SignedProperties', $signature);
        $certificate        = SignatureCertificate::fromBase64(self::text($xpath, 'ds:KeyInfo/ds:X509Data/ds:X509Certificate', $signature));
        $algorithms         = self::algorithmsSupported($xpath, $signature);
        $failures           = [
            ...($algorithms ? [] : [SignatureFailure::AlgorithmUnsupported]),
            ...self::references($xpath, $signature, $signed, $properties),
            ...self::certificate($xpath, $certificate, $properties),
        ];

        $signedInfo = self::elements($xpath, 'ds:SignedInfo', $signature)[0] ?? null;
        $value      = base64_decode(self::text($xpath, 'ds:SignatureValue', $signature), true);
        if ($algorithms && $certificate?->hasRsaKey() && ! $certificate->verifies($signedInfo instanceof DOMElement ? SignatureDigests::canonical($signedInfo) : '', (string) $value)) {
            $failures[] = SignatureFailure::ValueInvalid;
        }

        return new SignatureVerification(
            array_values(array_combine(array_map(static fn (SignatureFailure $failure): string => $failure->value, $failures), $failures)),
            $profile,
            $certificate?->subject(),
            $certificate?->serialNumber(),
            $certificate?->digest(),
        );
    }

    /**
     * @return array{SignatureProfile, DOMElement}|null
     */
    private static function placement(DOMDocument $document, DOMElement $signature): ?array
    {
        $root = $document->documentElement;
        if (! $root instanceof DOMElement || $signature->parentNode !== $root) {
            return null;
        }

        if (self::isFiscalRoot($root)) {
            return [SignatureProfile::Enveloped, $root];
        }

        $signed   = $signature->nextElementSibling;
        $detached = $root->namespaceURI === null && $root->localName === Fiscal::DETACHED_SIGNATURE_ROOT;

        if (! $detached || ! $signed instanceof DOMElement || ! self::isFiscalRoot($signed)) {
            return null;
        }

        return [SignatureProfile::InternallyDetached, $signed];
    }

    private static function algorithmsSupported(DOMXPath $xpath, DOMElement $signature): bool
    {
        return self::attributes($xpath, 'ds:SignedInfo/ds:CanonicalizationMethod', $signature) === [Fiscal::C14N_ALGORITHM]
            && self::attributes($xpath, 'ds:SignedInfo/ds:SignatureMethod', $signature) === [Fiscal::RSA_SHA256_ALGORITHM];
    }

    /**
     * @param  list<DOMElement>       $properties
     * @return list<SignatureFailure>
     */
    private static function references(DOMXPath $xpath, DOMElement $signature, DOMElement $signed, array $properties): array
    {
        $references = self::elements($xpath, 'ds:SignedInfo/ds:Reference', $signature);
        $expected   = \count($references) === 2 && \count($properties) === 1
            && self::points($xpath, $references[0], $signed, null)
            && self::points($xpath, $references[1], $properties[0], Fiscal::SIGNED_PROPERTIES_TYPE);
        if (! $expected) {
            return [SignatureFailure::ReferenceUnexpected];
        }

        $failures = [];
        foreach ([[$references[0], $signed], [$references[1], $properties[0]]] as [$reference, $target]) {
            $transforms = self::attributes($xpath, 'ds:Transforms/ds:Transform', $reference);
            if (array_diff($transforms, self::TRANSFORMS) !== [] || self::attributes($xpath, 'ds:DigestMethod', $reference) !== [Fiscal::SHA256_ALGORITHM]) {
                $failures[] = SignatureFailure::AlgorithmUnsupported;

                continue;
            }

            $enveloped = \in_array(Fiscal::ENVELOPED_SIGNATURE_TRANSFORM, $transforms, true);
            if (self::digest($target, $enveloped) !== self::text($xpath, 'ds:DigestValue', $reference)) {
                $failures[] = SignatureFailure::DigestMismatch;
            }
        }

        return $failures;
    }

    /**
     * @param  list<DOMElement>       $properties
     * @return list<SignatureFailure>
     */
    private static function certificate(DOMXPath $xpath, ?SignatureCertificate $certificate, array $properties): array
    {
        if (! $certificate instanceof SignatureCertificate) {
            return [SignatureFailure::CertificateInvalid];
        }

        $digest   = 'xades:SignedSignatureProperties/xades:SigningCertificate/xades:Cert/xades:CertDigest';
        $failures = $certificate->hasRsaKey() ? [] : [SignatureFailure::KeyUnsupported];
        $matches  = $properties === []
            || (self::attributes($xpath, $digest . '/ds:DigestMethod', $properties[0]) === [Fiscal::SHA256_ALGORITHM]
                && self::text($xpath, $digest . '/ds:DigestValue', $properties[0]) === $certificate->digest());

        return $matches ? $failures : [...$failures, SignatureFailure::CertificateMismatch];
    }

    private static function points(DOMXPath $xpath, DOMElement $reference, DOMElement $target, ?string $type): bool
    {
        $id      = $target->getAttribute('Id');
        $holders = array_filter(self::elements($xpath, '//*[@Id]'), static fn (DOMElement $element): bool => $element->getAttribute('Id') === $id);

        return $id !== ''
            && \count($holders) === 1
            && $reference->getAttribute('URI') === '#' . $id
            && ($reference->hasAttribute('Type') ? $reference->getAttribute('Type') : null) === $type;
    }

    private static function digest(DOMElement $target, bool $enveloped): string
    {
        $nodes = $enveloped ? ['query' => self::ENVELOPED_NODES, 'namespaces' => ['ds' => Fiscal::XMLDSIG_NAMESPACE]] : null;

        return SignatureDigests::of((string) $target->C14N(false, false, $nodes));
    }

    /**
     * @return list<string>
     */
    private static function attributes(DOMXPath $xpath, string $query, DOMNode $context): array
    {
        return array_map(static fn (DOMElement $element): string => $element->getAttribute('Algorithm'), self::elements($xpath, $query, $context));
    }

    private static function text(DOMXPath $xpath, string $query, DOMNode $context): string
    {
        $elements = self::elements($xpath, $query, $context);

        return \count($elements) === 1 ? $elements[0]->textContent : '';
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(DOMXPath $xpath, string $query, ?DOMNode $context = null): array
    {
        $nodes = iterator_to_array($xpath->query($query, $context) ?: []);

        return array_values(array_filter($nodes, static fn (mixed $node): bool => $node instanceof DOMElement));
    }

    private static function isFiscalRoot(DOMElement $element): bool
    {
        return $element->namespaceURI === Fiscal::XML_NAMESPACE && \in_array($element->localName, ['Dfe', 'Event'], true);
    }
}
