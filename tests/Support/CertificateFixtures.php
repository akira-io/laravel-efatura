<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\CertificateLoader;
use Akira\Efatura\Signing\SigningCredentials;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use OpenSSLAsymmetricKey;
use RuntimeException;

use const OPENSSL_ALGO_SHA256;
use const OPENSSL_KEYTYPE_EC;
use const OPENSSL_KEYTYPE_RSA;

final class CertificateFixtures
{
    public const string DISK = 'certs';

    public const string PASSPHRASE = ' e-Fatura test-only passphrase 5c1d ';

    public const string VALID_FROM = '2025-01-01T00:00:00Z';

    public const string VALID_TO = '2040-01-01T00:00:00Z';

    public const string SHORT_SERIAL = '1A2B3C';

    public const string LONG_SERIAL = '00FF0102030405060708090A0B0C0D0E0F101112';

    public const string SIGNING_USAGE = "\xC0";

    public const string CA_NAME = 'CN=e-Fatura Test CA,O=e-Fatura Test,C=CV';

    /** @var array<string, OpenSSLAsymmetricKey> */
    private static array $keys = [];

    /** @var array<string, TestCertificate> */
    private static array $certificates = [];

    public static function key(string $name = 'signer', int $bits = 2048): OpenSSLAsymmetricKey
    {
        return self::$keys[$name . $bits] ??= openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA])
            ?: throw new RuntimeException('Test key generation failed.');
    }

    public static function ecKey(): OpenSSLAsymmetricKey
    {
        return self::$keys['ec'] ??= openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'])
            ?: throw new RuntimeException('Test key generation failed.');
    }

    public static function ca(string $name = 'ca'): TestCertificate
    {
        return self::$certificates['ca-' . $name] ??= self::certificate(
            subject: self::rdns($name === 'ca' ? 'e-Fatura Test CA' : 'e-Fatura Other Test CA'),
            key: self::key($name),
            serialHex: '01',
            keyUsage: ["\x06", 1],
            authority: true,
        );
    }

    public static function signer(): TestCertificate
    {
        return self::$certificates['signer'] ??= self::issue();
    }

    public static function longSerialSigner(): TestCertificate
    {
        return self::$certificates['long-serial'] ??= self::issue(serialHex: self::LONG_SERIAL);
    }

    /**
     * @param array{0: string, 1: int}|null                         $keyUsage
     * @param list<list<array{0: string, 1: string, 2?: int}>>|null $issuerName
     */
    public static function issue(
        ?OpenSSLAsymmetricKey $key = null,
        string $serialHex = self::SHORT_SERIAL,
        ?array $keyUsage = [self::SIGNING_USAGE, 6],
        string $validFrom = self::VALID_FROM,
        string $validTo = self::VALID_TO,
        ?array $issuerName = null,
    ): TestCertificate {
        return self::certificate(self::rdns('e-Fatura Test Signer'), $key ?? self::key(), $serialHex, $keyUsage, false, $validFrom, $validTo, $issuerName);
    }

    public static function moment(string $instant, int $seconds = 0): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($instant)->addSeconds($seconds));
    }

    /**
     * @param array<string, string|null> $config
     */
    public static function store(string $format = 'pem', ?TestCertificate $certificate = null, array $config = []): void
    {
        $certificate ??= self::signer();
        $disk = Storage::fake(self::DISK);

        $files = match ($format) {
            'pem'           => ['certificate_path' => 'signer.crt', 'private_key_path' => 'signer.key', 'passphrase' => null],
            'pem-encrypted' => ['certificate_path' => 'signer.crt', 'private_key_path' => 'signer.key', 'passphrase' => self::PASSPHRASE],
            default         => ['certificate_path' => 'signer.p12', 'private_key_path' => null, 'passphrase' => self::PASSPHRASE],
        };

        $disk->put('signer.crt', $certificate->pem);
        $disk->put('signer.key', $format === 'pem' ? $certificate->keyPem() : $certificate->keyPem(self::PASSPHRASE));
        $disk->put('signer.p12', $certificate->pkcs12(self::PASSPHRASE));
        $disk->put('ca.pem', self::ca()->pem);

        config()->set(collect(['disk' => self::DISK, 'ca_bundle_path' => null, ...$files, ...$config])
            ->mapWithKeys(fn (?string $value, string $key): array => ['efatura.certificates.' . $key => $value])
            ->all());
        app()->forgetInstance(EfaturaConfig::class);
    }

    public static function load(): SigningCredentials
    {
        return resolve(CertificateLoader::class)->load(resolve(EfaturaConfig::class)->certificates);
    }

    public static function credentials(?TestCertificate $certificate = null): SigningCredentials
    {
        $now = CarbonImmutable::getTestNow();
        self::moment(self::VALID_FROM, 86_400);
        self::store(certificate: $certificate);

        try {
            return self::load();
        } finally {
            CarbonImmutable::setTestNow($now);
        }
    }

    /**
     * @return list<list<array{0: string, 1: string, 2?: int}>>
     */
    public static function rdns(string $commonName): array
    {
        return [[['2.5.4.6', 'CV', 0x13]], [['2.5.4.10', 'e-Fatura Test']], [['2.5.4.3', $commonName]]];
    }

    /**
     * @param list<list<array{0: string, 1: string, 2?: int}>>      $subject
     * @param array{0: string, 1: int}|null                         $keyUsage
     * @param list<list<array{0: string, 1: string, 2?: int}>>|null $issuerName
     */
    private static function certificate(
        array $subject,
        OpenSSLAsymmetricKey $key,
        string $serialHex,
        ?array $keyUsage,
        bool $authority,
        string $validFrom = self::VALID_FROM,
        string $validTo = self::VALID_TO,
        ?array $issuerName = null,
    ): TestCertificate {
        $issuer     = $authority ? null : self::ca();
        $extensions = [Der::sequence(Der::oid('2.5.29.19'), Der::boolean(true), Der::octetString($authority ? Der::sequence(Der::boolean(true)) : Der::sequence()))];
        if ($keyUsage !== null) {
            $extensions[] = Der::sequence(Der::oid('2.5.29.15'), Der::boolean(true), Der::octetString(Der::bitString($keyUsage[0], $keyUsage[1])));
        }

        $algorithm = Der::sequence(Der::oid('1.2.840.113549.1.1.11'), Der::null());
        $tbs       = Der::sequence(
            Der::explicit(0, Der::tlv(0x02, "\x02")),
            Der::integerHex($serialHex),
            $algorithm,
            Der::name($issuerName ?? ($issuer instanceof TestCertificate ? self::rdns('e-Fatura Test CA') : $subject)),
            Der::sequence(Der::utcTime(CarbonImmutable::parse($validFrom)), Der::utcTime(CarbonImmutable::parse($validTo))),
            Der::name($subject),
            Der::fromPem((string) (openssl_pkey_get_details($key) ?: [])['key']),
            Der::explicit(3, Der::sequence(...$extensions)),
        );

        openssl_sign($tbs, $signature, $issuer instanceof TestCertificate ? $issuer->key : $key, OPENSSL_ALGO_SHA256);

        return new TestCertificate(Der::pem(Der::sequence($tbs, $algorithm, Der::bitString((string) $signature))), $key);
    }
}
