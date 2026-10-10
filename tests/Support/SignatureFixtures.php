<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Contracts\XmlSigner;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Signing\SignedXml;
use Akira\Efatura\Signing\SigningCredentials;
use Akira\Efatura\Tests\Support\EventFixtures as E;
use Akira\Efatura\Tests\Support\XmlFixtures as X;
use Carbon\CarbonImmutable;
use DOMDocument;

final class SignatureFixtures
{
    public const string SIGNING_TIME = '2026-10-03T00:30:00Z';

    public const string LOCAL_SIGNING_TIME = '2026-10-02T23:30:00';

    public static function unsigned(string $kind = 'FTE'): string
    {
        CarbonImmutable::setTestNow(DocumentXmlGraphs::NOW);

        return $kind === 'FDC' ? X::eventXml(['iuds' => [E::iud()]]) : X::documentXml(X::minimal(DocumentType::Invoice));
    }

    public static function sign(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped, ?SigningCredentials $credentials = null): SignedXml
    {
        $credentials ??= CertificateFixtures::credentials();
        CarbonImmutable::setTestNow(self::SIGNING_TIME);

        return resolve(XmlSigner::class)->sign($xml, $credentials, $profile);
    }

    public static function document(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        $document->loadXML($xml);

        return $document;
    }

    public static function flipBase64(string $xml, string $query): string
    {
        $value    = SignatureVerifier::text(SignatureVerifier::xpath(self::document($xml)), $query);
        $position = intdiv(\strlen($value), 2);

        return str_replace($value, substr_replace($value, $value[$position] === 'A' ? 'B' : 'A', $position, 1), $xml);
    }

    /**
     * @return array<string, array{string, SignatureProfile}>
     */
    public static function documentsAndProfiles(): array
    {
        return [
            'enveloped invoice'           => ['FTE', SignatureProfile::Enveloped],
            'internally detached invoice' => ['FTE', SignatureProfile::InternallyDetached],
            'enveloped event'             => ['FDC', SignatureProfile::Enveloped],
            'internally detached event'   => ['FDC', SignatureProfile::InternallyDetached],
        ];
    }
}
