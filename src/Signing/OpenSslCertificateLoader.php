<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use Akira\Efatura\Configuration\CertificateConfig;
use Akira\Efatura\Contracts\CertificateLoader;
use Akira\Efatura\Exceptions\CertificateException;
use Akira\Efatura\Support\Fiscal;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Throwable;

use const OPENSSL_KEYTYPE_RSA;
use const X509_PURPOSE_ANY;

final readonly class OpenSslCertificateLoader implements CertificateLoader
{
    private const string DISK = 'efatura.certificates.disk';

    private const string CERTIFICATE = 'efatura.certificates.certificate_path';

    private const string PRIVATE_KEY = 'efatura.certificates.private_key_path';

    private const string PASSPHRASE = 'efatura.certificates.passphrase';

    private const string CA_BUNDLE = 'efatura.certificates.ca_bundle_path';

    public function __construct(private Factory $disks, private ClockInterface $clock) {}

    public function load(#[SensitiveParameter] CertificateConfig $config): SigningCredentials
    {
        OpenSslErrors::drain();

        try {
            $disk     = $this->disk($config->disk);
            $contents = $this->read($disk, $config->certificatePath, self::CERTIFICATE);

            [$certificate, $privateKey] = Str::contains($contents, '-----BEGIN CERTIFICATE-----')
                ? $this->fromPem($disk, $contents, $config)
                : $this->fromPkcs12($contents, $config);

            $credentials = $this->verified($certificate, $privateKey);
            $this->verifyChain($disk, $config->caBundlePath, $certificate);

            return $credentials;
        } finally {
            OpenSslErrors::drain();
        }
    }

    private function disk(string $name): Filesystem
    {
        try {
            return $this->disks->disk($name);
        } catch (Throwable) {
            throw new CertificateException('certificate.unreadable', self::DISK);
        }
    }

    private function read(#[SensitiveParameter] Filesystem $disk, ?string $path, string $field): string
    {
        if ($path === null) {
            throw new CertificateException('certificate.missing', $field);
        }

        try {
            $contents = $disk->get($path);
        } catch (Throwable) {
            $contents = null;
        }

        if ($contents === null || $contents === '') {
            throw new CertificateException('certificate.unreadable', $field);
        }

        return $contents;
    }

    /**
     * @return array{OpenSSLCertificate, OpenSSLAsymmetricKey}
     */
    private function fromPem(
        #[SensitiveParameter]
        Filesystem $disk,
        #[SensitiveParameter]
        string $contents,
        #[SensitiveParameter]
        CertificateConfig $config,
    ): array {
        $certificate = @openssl_x509_read($contents);
        if (! $certificate instanceof OpenSSLCertificate) {
            throw new CertificateException('certificate.invalid', self::CERTIFICATE);
        }

        $keyPem     = $this->read($disk, $config->privateKeyPath, self::PRIVATE_KEY);
        $privateKey = openssl_pkey_get_private($keyPem, $config->passphrase ?? '');
        if ($privateKey === false) {
            throw Str::contains($keyPem, 'ENCRYPTED')
                ? new CertificateException('certificate.passphrase_invalid', self::PASSPHRASE)
                : new CertificateException('certificate.invalid', self::PRIVATE_KEY);
        }

        return [$certificate, $privateKey];
    }

    /**
     * @return array{OpenSSLCertificate, OpenSSLAsymmetricKey}
     */
    private function fromPkcs12(#[SensitiveParameter] string $contents, #[SensitiveParameter] CertificateConfig $config): array
    {
        if ($config->privateKeyPath !== null) {
            throw new CertificateException('certificate.invalid', self::PRIVATE_KEY);
        }

        if (! openssl_pkcs12_read($contents, $bundle, $config->passphrase ?? '')) {
            $errorCode = OpenSslErrors::pkcs12Failure(OpenSslErrors::drain());

            throw new CertificateException($errorCode, $errorCode === 'certificate.passphrase_invalid' ? self::PASSPHRASE : self::CERTIFICATE);
        }

        $bundle      = \is_array($bundle) ? $bundle : [];
        $certificate = @openssl_x509_read(\is_string($bundle['cert'] ?? null) ? $bundle['cert'] : '');
        $privateKey  = openssl_pkey_get_private(\is_string($bundle['pkey'] ?? null) ? $bundle['pkey'] : '');

        return $certificate instanceof OpenSSLCertificate && $privateKey !== false
            ? [$certificate, $privateKey]
            : throw new CertificateException('certificate.invalid', self::CERTIFICATE);
    }

    private function verified(OpenSSLCertificate $certificate, #[SensitiveParameter] OpenSSLAsymmetricKey $privateKey): SigningCredentials
    {
        if (! openssl_x509_check_private_key($certificate, $privateKey)) {
            throw new CertificateException('certificate.key_mismatch', self::CERTIFICATE);
        }

        $key = openssl_pkey_get_details($privateKey) ?: [];
        if (($key['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || ($key['bits'] ?? 0) < Fiscal::MIN_RSA_KEY_BITS) {
            throw new CertificateException('certificate.key_unsupported', self::CERTIFICATE);
        }

        $credentials = SigningCredentials::fromCertificate($certificate, $privateKey);
        $now         = $this->clock->now()->getTimestamp();
        if ($now < $credentials->validFrom->getTimestamp()) {
            throw new CertificateException('certificate.not_yet_valid', self::CERTIFICATE);
        }

        if ($now >= $credentials->validTo->getTimestamp()) {
            throw new CertificateException('certificate.expired', self::CERTIFICATE);
        }

        $extensions = (openssl_x509_parse($certificate) ?: [])['extensions'] ?? null;
        $usage      = \is_array($extensions) ? ($extensions['keyUsage'] ?? null) : null;
        if (\is_string($usage) && ! Str::contains($usage, ['Digital Signature', 'Non Repudiation'])) {
            throw new CertificateException('certificate.usage_invalid', self::CERTIFICATE);
        }

        return $credentials;
    }

    private function verifyChain(#[SensitiveParameter] Filesystem $disk, ?string $bundlePath, OpenSSLCertificate $certificate): void
    {
        if ($bundlePath === null) {
            return;
        }

        $bundle = $this->read($disk, $bundlePath, self::CA_BUNDLE);
        $file   = (string) tempnam(sys_get_temp_dir(), 'efatura');

        try {
            file_put_contents($file, $bundle);
            $trusted = openssl_x509_checkpurpose($certificate, X509_PURPOSE_ANY, [$file]);
        } finally {
            @unlink($file);
        }

        if ($trusted !== true) {
            throw new CertificateException('certificate.untrusted', self::CA_BUNDLE);
        }
    }
}
