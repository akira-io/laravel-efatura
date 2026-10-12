<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Enums\SignatureFailure;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Signing\SignatureCertificate;
use Akira\Efatura\Signing\SignatureDigests;
use Akira\Efatura\Signing\SignatureStructure;
use Akira\Efatura\Signing\SignatureVerification;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\SafeXmlParser;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final readonly class VerifyXmlSignatureAction
{
    private const array PROPERTIES_TRANSFORMS = [[], [Fiscal::C14N_ALGORITHM]];

    public function __construct(private SafeXmlParser $parser) {}

    public function handle(DOMDocument|string $xml): SignatureVerification
    {
        $document = $xml instanceof DOMDocument ? $xml : $this->parser->parse($xml);
        $xpath    = new DOMXPath($document);
        $xpath->registerNamespace('ds', Fiscal::XMLDSIG_NAMESPACE);
        $xpath->registerNamespace('xades', Fiscal::XADES_NAMESPACE);

        $signatures = self::elements($xpath, '//ds:Signature');
        if (\count($signatures) !== 1) {
            return new SignatureVerification([$signatures === [] ? SignatureFailure::Missing : SignatureFailure::Multiple]);
        }

        $placement = self::placement($document, $signatures[0]);
        if ($placement === null) {
            return new SignatureVerification([SignatureFailure::Placement]);
        }

        [$profile, $signed] = $placement;
        $structure          = SignatureStructure::of($signatures[0]);
        if (! $structure instanceof SignatureStructure) {
            return new SignatureVerification([SignatureFailure::StructureUnexpected], $profile);
        }

        $certificate = SignatureCertificate::fromBase64($structure->certificate->textContent);
        $algorithms  = self::algorithmsSupported($xpath, $structure->signedInfo);
        $failures    = [
            ...($algorithms ? [] : [SignatureFailure::AlgorithmUnsupported]),
            ...self::references($document, $xpath, $profile, $signed, $structure),
            ...self::certificate($xpath, $certificate, $structure->signedProperties),
        ];

        $value = (string) base64_decode($structure->signatureValue->textContent, true);
        if ($algorithms && $certificate?->hasRsaKey() && ! $certificate->verifies(SignatureDigests::canonical($structure->signedInfo), $value)) {
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
            return $root->lastElementChild === $signature ? [SignatureProfile::Enveloped, $root] : null;
        }

        $signed   = $signature->nextElementSibling;
        $detached = $root->namespaceURI === null && $root->localName === Fiscal::DETACHED_SIGNATURE_ROOT && $root->firstElementChild === $signature;

        return $detached && $signed instanceof DOMElement && ! $signed->nextElementSibling instanceof DOMElement && self::isFiscalRoot($signed)
            ? [SignatureProfile::InternallyDetached, $signed]
            : null;
    }

    private static function algorithmsSupported(DOMXPath $xpath, DOMElement $signedInfo): bool
    {
        return self::attributes($xpath, 'ds:CanonicalizationMethod', $signedInfo) === [Fiscal::C14N_ALGORITHM]
            && self::attributes($xpath, 'ds:SignatureMethod', $signedInfo) === [Fiscal::RSA_SHA256_ALGORITHM];
    }

    /**
     * @return list<SignatureFailure>
     */
    private static function references(
        DOMDocument $document,
        DOMXPath $xpath,
        SignatureProfile $profile,
        DOMElement $signed,
        SignatureStructure $structure,
    ): array {
        $references       = $structure->references;
        $signedProperties = $structure->signedProperties;
        if (\count($references) !== 2) {
            return [SignatureFailure::ReferenceUnexpected];
        }

        [$data, $properties] = $references[0]->hasAttribute('Type') ? array_reverse($references) : $references;
        if (! self::points($xpath, $data, $signed, null) || ! self::points($xpath, $properties, $signedProperties, Fiscal::SIGNED_PROPERTIES_TYPE)) {
            return [SignatureFailure::ReferenceUnexpected];
        }

        $failures = [];
        $checks   = [[$data, $signed, self::dataTransforms($profile)], [$properties, $signedProperties, self::PROPERTIES_TRANSFORMS]];
        foreach ($checks as [$reference, $target, $allowed]) {
            $transforms = self::attributes($xpath, 'ds:Transforms/ds:Transform', $reference);
            if (! \in_array($transforms, $allowed, true) || self::attributes($xpath, 'ds:DigestMethod', $reference) !== [Fiscal::SHA256_ALGORITHM]) {
                $failures[] = SignatureFailure::AlgorithmUnsupported;

                continue;
            }

            $enveloped = ($transforms[0] ?? null) === Fiscal::ENVELOPED_SIGNATURE_TRANSFORM;
            if (self::digest($document, $target, $enveloped) !== self::text($xpath, 'ds:DigestValue', $reference)) {
                $failures[] = SignatureFailure::DigestMismatch;
            }
        }

        return $failures;
    }

    /**
     * @return list<list<string>>
     */
    private static function dataTransforms(SignatureProfile $profile): array
    {
        return $profile === SignatureProfile::Enveloped
            ? [[Fiscal::ENVELOPED_SIGNATURE_TRANSFORM], [Fiscal::ENVELOPED_SIGNATURE_TRANSFORM, Fiscal::C14N_ALGORITHM]]
            : self::PROPERTIES_TRANSFORMS;
    }

    /**
     * @return list<SignatureFailure>
     */
    private static function certificate(DOMXPath $xpath, ?SignatureCertificate $certificate, DOMElement $properties): array
    {
        if (! $certificate instanceof SignatureCertificate) {
            return [SignatureFailure::CertificateInvalid];
        }

        $digest   = 'xades:SignedSignatureProperties/xades:SigningCertificate/xades:Cert/xades:CertDigest';
        $failures = $certificate->hasRsaKey() ? [] : [SignatureFailure::KeyUnsupported];
        $matches  = self::attributes($xpath, $digest . '/ds:DigestMethod', $properties) === [Fiscal::SHA256_ALGORITHM]
            && self::text($xpath, $digest . '/ds:DigestValue', $properties) === $certificate->digest();

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

    private static function digest(DOMDocument $document, DOMElement $target, bool $enveloped): string
    {
        if (! $enveloped) {
            return SignatureDigests::node($target);
        }

        $copy      = clone $document;
        $signature = $copy->documentElement?->lastElementChild;
        if ($signature instanceof DOMElement) {
            $signature->parentNode?->removeChild($signature);
        }

        return SignatureDigests::node($copy->documentElement ?? $copy);
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

        return \count($elements) === 1 ? trim($elements[0]->textContent) : '';
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
