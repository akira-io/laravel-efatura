<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use DOMElement;
use SensitiveParameter;

final readonly class SignedPropertiesWriter
{
    public function append(DOMElement $object, #[SensitiveParameter] SigningCredentials $credentials, CarbonImmutable $signingTime): DOMElement
    {
        $qualifying = self::xades($object, 'QualifyingProperties', attributes: ['Target' => '#' . Fiscal::SIGNATURE_ID]);
        $signed     = self::xades($qualifying, 'SignedProperties', attributes: ['Id' => Fiscal::SIGNED_PROPERTIES_ID]);
        $signature  = self::xades($signed, 'SignedSignatureProperties');

        self::xades($signature, 'SigningTime', Fiscal::format($signingTime, Fiscal::DATE_TIME_FORMAT, instant: true));

        $certificate = self::xades(self::xades($signature, 'SigningCertificate'), 'Cert');
        $digest      = self::xades($certificate, 'CertDigest');
        SignatureElement::append($digest, Fiscal::XMLDSIG_NAMESPACE, 'ds:DigestMethod', attributes: ['Algorithm' => Fiscal::SHA256_ALGORITHM]);
        SignatureElement::append($digest, Fiscal::XMLDSIG_NAMESPACE, 'ds:DigestValue', $credentials->certificateDigest());

        $issuerSerial = self::xades($certificate, 'IssuerSerial');
        SignatureElement::append($issuerSerial, Fiscal::XMLDSIG_NAMESPACE, 'ds:X509IssuerName', $credentials->issuerName);
        SignatureElement::append($issuerSerial, Fiscal::XMLDSIG_NAMESPACE, 'ds:X509SerialNumber', $credentials->serialNumber);

        $dataObjects = self::xades($signed, 'SignedDataObjectProperties');
        $format      = self::xades($dataObjects, 'DataObjectFormat', attributes: ['ObjectReference' => '#' . Fiscal::DATA_REFERENCE_ID]);
        self::xades($format, 'MimeType', 'text/xml');

        return $signed;
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function xades(DOMElement $parent, string $localName, ?string $text = null, array $attributes = []): DOMElement
    {
        return SignatureElement::append($parent, Fiscal::XADES_NAMESPACE, 'xades:' . $localName, $text, $attributes);
    }
}
