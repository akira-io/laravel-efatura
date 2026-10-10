<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use OpenSSLAsymmetricKey;

final readonly class TestCertificate
{
    public function __construct(public string $pem, public OpenSSLAsymmetricKey $key) {}

    public function der(): string
    {
        return Der::fromPem($this->pem);
    }

    public function keyPem(?string $passphrase = null): string
    {
        openssl_pkey_export($this->key, $pem, $passphrase);

        return (string) $pem;
    }

    public function pkcs12(string $passphrase): string
    {
        openssl_pkcs12_export($this->pem, $pkcs12, $this->key, $passphrase);

        return (string) $pkcs12;
    }

    public function serialHex(): string
    {
        return (string) (openssl_x509_parse($this->pem) ?: [])['serialNumberHex'];
    }
}
