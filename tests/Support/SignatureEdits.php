<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Support\Fiscal;
use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

use const OPENSSL_ALGO_SHA256;

final class SignatureEdits
{
    /**
     * @param Closure(DOMDocument, DOMXPath): void $edit
     */
    public static function edit(string $xml, Closure $edit, bool $resign = false): string
    {
        $document = SignatureFixtures::document($xml);
        $xpath    = SignatureVerifier::xpath($document);
        $edit($document, $xpath);

        if ($resign) {
            $signedInfo = self::element($xpath, '//ds:SignedInfo');
            openssl_sign((string) $signedInfo->C14N(false, false), $signature, CertificateFixtures::signer()->key, OPENSSL_ALGO_SHA256);
            self::element($xpath, '//ds:SignatureValue')->textContent = base64_encode((string) $signature);
        }

        return (string) $document->saveXML();
    }

    public static function element(DOMXPath $xpath, string $query): DOMElement
    {
        $element = ($xpath->query($query) ?: null)?->item(0);

        return $element instanceof DOMElement ? $element : throw new RuntimeException('No element for ' . $query . '.');
    }

    public static function setAlgorithm(string $query, string $algorithm): Closure
    {
        return self::setAttribute($query, 'Algorithm', $algorithm);
    }

    public static function setAttribute(string $query, string $name, string $value): Closure
    {
        return static function (DOMDocument $document, DOMXPath $xpath) use ($query, $name, $value): void {
            self::element($xpath, $query)->setAttribute($name, $value);
        };
    }

    public static function appendTransform(string $reference, string $algorithm): Closure
    {
        return static function (DOMDocument $document, DOMXPath $xpath) use ($reference, $algorithm): void {
            $transform = $document->createElementNS(Fiscal::XMLDSIG_NAMESPACE, 'ds:Transform');
            $transform->setAttribute('Algorithm', $algorithm);

            $transforms = ($xpath->query($reference . '/ds:Transforms') ?: null)?->item(0);
            if (! $transforms instanceof DOMElement) {
                $transforms = $document->createElementNS(Fiscal::XMLDSIG_NAMESPACE, 'ds:Transforms');
                self::element($xpath, $reference)->insertBefore($transforms, self::element($xpath, $reference . '/ds:DigestMethod'));
            }

            $transforms->appendChild($transform);
        };
    }

    public static function replaceCertificate(string $der): Closure
    {
        return static function (DOMDocument $document, DOMXPath $xpath) use ($der): void {
            self::element($xpath, '//ds:X509Certificate')->textContent = $der;
        };
    }
}
