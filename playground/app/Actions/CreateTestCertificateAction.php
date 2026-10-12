<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\TestCertificate;
use RuntimeException;

final readonly class CreateTestCertificateAction
{
    private const array SUBJECT = [
        'countryName' => 'CV',
        'organizationName' => 'e-Fatura Playground',
        'commonName' => 'e-Fatura Playground Test Signer (not ICP-CV)',
    ];

    public function handle(int $validDays): TestCertificate
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA])
            ?: throw new RuntimeException('The test key could not be generated.');
        $request = openssl_csr_new(self::SUBJECT, $key, ['digest_alg' => 'sha256'])
            ?: throw new RuntimeException('The test certificate request could not be created.');
        $certificate = openssl_csr_sign($request, null, $key, $validDays, ['digest_alg' => 'sha256'], random_int(1, PHP_INT_MAX))
            ?: throw new RuntimeException('The test certificate could not be signed.');

        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($key, $privateKeyPem);

        return new TestCertificate((string) $certificatePem, (string) $privateKeyPem);
    }
}
