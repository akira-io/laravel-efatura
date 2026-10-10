<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use Akira\Efatura\Contracts\XmlSigner;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\SignatureException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Xml\SafeXmlParser;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

use const OPENSSL_ALGO_SHA256;

final readonly class XadesBesXmlSigner implements XmlSigner
{
    private const string DETACHED_ROOT = 'internally-detached';

    public function __construct(private SafeXmlParser $parser, private SignedPropertiesWriter $properties, private ClockInterface $clock) {}

    public function sign(
        string $xml,
        #[SensitiveParameter]
        SigningCredentials $credentials,
        SignatureProfile $profile = SignatureProfile::Enveloped,
    ): SignedXml {
        $source              = $this->parser->parse($xml);
        [$root, $id]         = self::root($source);
        [$document, $signed] = $profile === SignatureProfile::Enveloped ? [$source, $root] : self::detached($root);
        $document->encoding  = 'UTF-8';

        $dataDigest = self::digest($signed);
        $signature  = new DOMElement('ds:Signature', namespace: Fiscal::XMLDSIG_NAMESPACE);
        $profile === SignatureProfile::Enveloped ? $root->appendChild($signature) : $signed->parentNode?->insertBefore($signature, $signed);
        $signature->setAttribute('Id', Fiscal::SIGNATURE_ID);

        $signedInfo = self::ds($signature, 'SignedInfo');
        self::ds($signedInfo, 'CanonicalizationMethod', attributes: ['Algorithm' => Fiscal::C14N_ALGORITHM]);
        self::ds($signedInfo, 'SignatureMethod', attributes: ['Algorithm' => Fiscal::RSA_SHA256_ALGORITHM]);
        $dataTransform = $profile === SignatureProfile::Enveloped ? Fiscal::ENVELOPED_SIGNATURE_TRANSFORM : null;
        self::reference($signedInfo, ['Id' => Fiscal::DATA_REFERENCE_ID, 'URI' => '#' . $id], $dataTransform, $dataDigest);
        $propertiesReference = ['URI' => '#' . Fiscal::SIGNED_PROPERTIES_ID, 'Type' => Fiscal::SIGNED_PROPERTIES_TYPE];
        $propertiesDigest    = self::reference($signedInfo, $propertiesReference, Fiscal::C14N_ALGORITHM);

        $signatureValue = self::ds($signature, 'SignatureValue');
        self::ds(self::ds(self::ds($signature, 'KeyInfo'), 'X509Data'), 'X509Certificate', base64_encode($credentials->certificateDer));

        $signingTime      = Fiscal::local(CarbonImmutable::instance($this->clock->now()));
        $signedProperties = $this->properties->append(self::ds($signature, 'Object'), $credentials, $signingTime);
        $propertiesDigest->appendChild($document->createTextNode(self::digest($signedProperties)));
        $signatureValue->appendChild($document->createTextNode(self::signatureOf($signedInfo, $credentials)));

        return new SignedXml(
            (string) $document->saveXML(),
            $id,
            $profile,
            $signingTime,
            $credentials->certificateDigest(),
            $credentials->issuerName,
            $credentials->serialNumber,
        );
    }

    /**
     * @return array{DOMElement, string}
     */
    private static function root(DOMDocument $document): array
    {
        $root = $document->documentElement;
        $name = $root?->namespaceURI === Fiscal::XML_NAMESPACE ? $root->localName : null;
        if (! $root instanceof DOMElement || ! \in_array($name, ['Dfe', 'Event'], true)) {
            throw new SignatureException('signature.unsupported_root');
        }

        $id = $root->getAttribute('Id');
        if (! ($name === 'Dfe' ? FiscalRules::isIud($id) : FiscalRules::isEventId($id))) {
            throw new SignatureException('signature.missing_id');
        }

        if ($document->getElementsByTagNameNS(Fiscal::XMLDSIG_NAMESPACE, 'Signature')->length > 0) {
            throw new SignatureException('signature.already_signed');
        }

        return [$root, $id];
    }

    /**
     * @return array{DOMDocument, DOMNode}
     */
    private static function detached(DOMElement $root): array
    {
        $document  = new DOMDocument('1.0', 'UTF-8');
        $container = new DOMElement(self::DETACHED_ROOT);
        $document->appendChild($container);
        $signed = $document->importNode($root, true);
        $container->appendChild($signed);

        return [$document, $signed];
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function reference(DOMElement $signedInfo, array $attributes, ?string $transform, ?string $digest = null): DOMElement
    {
        $reference = self::ds($signedInfo, 'Reference', attributes: $attributes);
        if ($transform !== null) {
            self::ds(self::ds($reference, 'Transforms'), 'Transform', attributes: ['Algorithm' => $transform]);
        }

        self::ds($reference, 'DigestMethod', attributes: ['Algorithm' => Fiscal::SHA256_ALGORITHM]);

        return self::ds($reference, 'DigestValue', $digest);
    }

    private static function signatureOf(DOMElement $signedInfo, #[SensitiveParameter] SigningCredentials $credentials): string
    {
        $canonical = (string) $signedInfo->C14N(false, false);
        OpenSslErrors::drain();

        try {
            openssl_sign($canonical, $signature, $credentials->privateKey, OPENSSL_ALGO_SHA256);
            $signature = \is_string($signature) ? $signature : '';
            $signed    = openssl_verify($canonical, $signature, $credentials->certificate, OPENSSL_ALGO_SHA256) === 1;
        } finally {
            OpenSslErrors::drain();
        }

        if (! $signed) {
            throw new SignatureException('signature.failed');
        }

        return base64_encode($signature);
    }

    private static function digest(DOMNode $node): string
    {
        return base64_encode(hash('sha256', (string) $node->C14N(false, false), true));
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function ds(DOMElement $parent, string $localName, ?string $text = null, array $attributes = []): DOMElement
    {
        return SignatureElement::append($parent, Fiscal::XMLDSIG_NAMESPACE, 'ds:' . $localName, $text, $attributes);
    }
}
