<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use Brick\Math\BigInteger;
use Illuminate\Support\Str;
use OpenSSLCertificate;

use const OPENSSL_ALGO_SHA256;
use const OPENSSL_KEYTYPE_RSA;

final readonly class SignatureCertificate
{
    private function __construct(private OpenSSLCertificate $certificate, public string $der) {}

    public static function fromBase64(string $encoded): ?self
    {
        $der = base64_decode(Str::of($encoded)->replaceMatches('/\s+/', '')->value(), true);
        if ($der === false) {
            return null;
        }

        OpenSslErrors::drain();
        $certificate = @openssl_x509_read("-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n");
        OpenSslErrors::drain();

        return $certificate instanceof OpenSSLCertificate ? new self($certificate, $der) : null;
    }

    public function subject(): string
    {
        return DistinguishedName::subjectOf($this->der);
    }

    public function serialNumber(): string
    {
        $serial = (openssl_x509_parse($this->certificate) ?: [])['serialNumberHex'] ?? '0';

        return BigInteger::fromBase(\is_string($serial) ? $serial : '0', 16)->toBase(10);
    }

    public function digest(): string
    {
        return base64_encode(hash('sha256', $this->der, true));
    }

    public function hasRsaKey(): bool
    {
        $key = openssl_pkey_get_public($this->certificate);

        return $key !== false && (openssl_pkey_get_details($key) ?: [])['type'] === OPENSSL_KEYTYPE_RSA;
    }

    public function verifies(string $data, string $signature): bool
    {
        try {
            return openssl_verify($data, $signature, $this->certificate, OPENSSL_ALGO_SHA256) === 1;
        } finally {
            OpenSslErrors::drain();
        }
    }
}
