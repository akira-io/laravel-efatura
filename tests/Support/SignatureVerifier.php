<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Support\Fiscal;
use DOMDocument;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;
use Throwable;

final class SignatureVerifier
{
    public static function verify(string $xml): void
    {
        $document = new DOMDocument;
        $document->loadXML($xml);

        $xpath = self::xpath($document);

        $certificate = (string) base64_decode(self::text($xpath, '//ds:Signature/ds:KeyInfo/ds:X509Data/ds:X509Certificate'), true);
        $key         = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'public']);
        $key->loadKey(Der::pem($certificate), false, true);

        new XMLSecurityDSig()->verifyDocument($key, $document);

        if (self::text($xpath, '//xades:SigningCertificate/xades:Cert/xades:CertDigest/ds:DigestValue') !== base64_encode(hash('sha256', $certificate, true))) {
            throw new RuntimeException('The signing certificate digest does not match the certificate in KeyInfo.');
        }
    }

    public static function isValid(string $xml): bool
    {
        try {
            self::verify($xml);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public static function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ds', Fiscal::XMLDSIG_NAMESPACE);
        $xpath->registerNamespace('xades', Fiscal::XADES_NAMESPACE);
        $xpath->registerNamespace('e', Fiscal::XML_NAMESPACE);

        return $xpath;
    }

    public static function text(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        if ($nodes === false || $nodes->length !== 1) {
            throw new RuntimeException('Expected exactly one node for ' . $query . '.');
        }

        return (string) $nodes->item(0)?->textContent;
    }
}
