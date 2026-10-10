<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\CertificateLoader;
use Akira\Efatura\Exceptions\CertificateException;
use Akira\Efatura\Signing\OpenSslCertificateLoader;
use Akira\Efatura\Signing\SigningCredentials;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    C::moment(C::VALID_FROM, 86_400);
});

it('is the certificate loader the container resolves', function (): void {
    expect(resolve(CertificateLoader::class))->toBeInstanceOf(OpenSslCertificateLoader::class);
});

it('loads pem and pkcs12 credentials with their issuer, serial and validity', function (string $format): void {
    C::store($format);
    $credentials = C::load();

    expect($credentials->issuerName)->toBe(C::CA_NAME)
        ->and($credentials->serialNumber)->toBe('1715004')
        ->and($credentials->validFrom->toIso8601ZuluString())->toBe('2025-01-01T00:00:00Z')
        ->and($credentials->validTo->toIso8601ZuluString())->toBe('2040-01-01T00:00:00Z')
        ->and($credentials->validFrom->getTimezone()->getName())->toBe('Atlantic/Cape_Verde')
        ->and($credentials->certificateDer)->toBe(C::signer()->der())
        ->and(openssl_x509_check_private_key($credentials->certificate, $credentials->privateKey))->toBeTrue();
})->with(['pem', 'pem-encrypted', 'pkcs12']);

it('preserves the whitespace of the passphrase', function (string $format): void {
    C::store($format, config: ['passphrase' => trim(C::PASSPHRASE)]);

    expect(fn (): SigningCredentials => C::load())->toThrow(CertificateException::class, 'certificate.passphrase_invalid: efatura.certificates.passphrase');
})->with(['pem-encrypted', 'pkcs12']);

it('reads the certificate from the default disk when none is configured', function (): void {
    C::store();
    config()->set(['filesystems.default' => C::DISK, 'efatura.certificates.disk' => null]);

    expect(C::load()->serialNumber)->toBe('1715004');
});

it('writes a twenty byte serial number in exact decimal', function (): void {
    C::store(certificate: C::longSerialSigner());

    expect(C::load()->serialNumber)->toBe(BigInteger::fromBase('FF0102030405060708090A0B0C0D0E0F101112', 16)->toBase(10))
        ->and(C::load()->serialNumber)->toHaveLength(46);
});

it('accepts a certificate without a key usage extension', function (): void {
    C::store(certificate: C::issue(keyUsage: null));

    expect(C::load()->issuerName)->toBe(C::CA_NAME);
});

it('accepts a key usage limited to non repudiation', function (): void {
    C::store(certificate: C::issue(keyUsage: ["\x40", 6]));

    expect(C::load()->issuerName)->toBe(C::CA_NAME);
});

it('verifies the chain against the configured ca bundle', function (): void {
    C::store(config: ['ca_bundle_path' => 'ca.pem']);

    expect(C::load()->issuerName)->toBe(C::CA_NAME);
});

it('accepts the certificate from its first second and refuses it from its last', function (): void {
    C::store();
    C::moment(C::VALID_FROM);
    $first = C::load();
    C::moment(C::VALID_TO, -1);

    expect($first->serialNumber)->toBe(C::load()->serialNumber);
});

it('refuses unusable signing material with a stable code and field', function (Closure $arrange, string $errorCode, string $field): void {
    $arrange();

    expect(fn (): SigningCredentials => C::load())->toThrow(function (CertificateException $exception) use ($errorCode, $field): void {
        expect($exception->errorCode)->toBe($errorCode)
            ->and($exception->field)->toBe('efatura.certificates.' . $field)
            ->and($exception->getMessage())->toBe($errorCode . ': efatura.certificates.' . $field)
            ->and($exception->context)->toBe([])
            ->and($exception->getPrevious())->toBeNull();
    })->and(openssl_error_string())->toBeFalse();
})->with([
    'missing certificate path' => [fn () => C::store(config: ['certificate_path' => null]), 'certificate.missing', 'certificate_path'],
    'missing certificate file' => [fn () => C::store(config: ['certificate_path' => 'absent.crt']), 'certificate.unreadable', 'certificate_path'],
    'disk that throws on read' => [static function (): void {
        C::store();
        Storage::fake(C::DISK, ['throw' => true]);
    }, 'certificate.unreadable', 'certificate_path'],
    'unknown disk'     => [fn () => C::store(config: ['disk' => 'absent-disk']), 'certificate.unreadable', 'disk'],
    'missing key path' => [fn () => C::store(config: ['private_key_path' => null]), 'certificate.missing', 'private_key_path'],
    'missing key file' => [fn () => C::store(config: ['private_key_path' => 'absent.key']), 'certificate.unreadable', 'private_key_path'],
    'truncated pem'    => [static function (): void {
        C::store(config: ['certificate_path' => 'broken.crt']);
        Storage::disk(C::DISK)->put('broken.crt', substr(C::signer()->pem, 0, 200));
    }, 'certificate.invalid', 'certificate_path'],
    'unreadable key' => [static function (): void {
        C::store();
        Storage::disk(C::DISK)->put('signer.key', 'not a private key');
    }, 'certificate.invalid', 'private_key_path'],
    'wrong pem passphrase'   => [fn () => C::store('pem-encrypted', config: ['passphrase' => 'wrong']), 'certificate.passphrase_invalid', 'passphrase'],
    'missing pem passphrase' => [fn () => C::store('pem-encrypted', config: ['passphrase' => null]), 'certificate.passphrase_invalid', 'passphrase'],
    'corrupted pkcs12'       => [static function (): void {
        C::store('pkcs12');
        Storage::disk(C::DISK)->put('signer.p12', substr(C::signer()->pkcs12(C::PASSPHRASE), 0, 200));
    }, 'certificate.invalid', 'certificate_path'],
    'wrong pkcs12 passphrase' => [fn () => C::store('pkcs12', config: ['passphrase' => 'wrong']), 'certificate.passphrase_invalid', 'passphrase'],
    'pkcs12 with a key path'  => [fn () => C::store('pkcs12', config: ['private_key_path' => 'signer.key']), 'certificate.invalid', 'private_key_path'],
    'key of another signer'   => [static function (): void {
        C::store();
        Storage::disk(C::DISK)->put('signer.key', C::issue(C::key('other'))->keyPem());
    }, 'certificate.key_mismatch', 'certificate_path'],
    'elliptic curve key'      => [fn () => C::store(certificate: C::issue(C::ecKey())), 'certificate.key_unsupported', 'certificate_path'],
    'short rsa key'           => [fn () => C::store(certificate: C::issue(C::key('short', 1024))), 'certificate.key_unsupported', 'certificate_path'],
    'encipherment only usage' => [fn () => C::store(certificate: C::issue(keyUsage: ["\x20", 5])), 'certificate.usage_invalid', 'certificate_path'],
    'not yet valid'           => [static function (): void {
        C::store();
        C::moment(C::VALID_FROM, -1);
    }, 'certificate.not_yet_valid', 'certificate_path'],
    'expired' => [static function (): void {
        C::store();
        C::moment(C::VALID_TO);
    }, 'certificate.expired', 'certificate_path'],
    'untrusted issuer' => [static function (): void {
        C::store(config: ['ca_bundle_path' => 'other-ca.pem']);
        Storage::disk(C::DISK)->put('other-ca.pem', C::ca('other')->pem);
    }, 'certificate.untrusted', 'ca_bundle_path'],
    'missing ca bundle' => [fn () => C::store(config: ['ca_bundle_path' => 'absent.pem']), 'certificate.unreadable', 'ca_bundle_path'],
]);

it('keeps the passphrase, the private key and the pkcs12 bytes out of every failure', function (string $format, ?string $passphrase): void {
    $original = ini_set('zend.exception_ignore_args', '0');
    C::store($format, config: ['passphrase' => $passphrase]);
    $secrets = [C::PASSPHRASE, trim(C::PASSPHRASE), C::signer()->keyPem(), C::signer()->keyPem(C::PASSPHRASE), C::signer()->pkcs12(C::PASSPHRASE)];

    try {
        expect(fn (): SigningCredentials => C::load())->toThrow(function (CertificateException $exception) use ($secrets): void {
            $dumps = [
                $exception->getMessage(),
                print_r($exception->context, true),
                var_export($exception->context, true),
                (string) json_encode($exception),
                print_r(collect($exception->getTrace())->filter(fn (array $frame): bool => str_starts_with($frame['class'] ?? '', 'Akira\Efatura\Signing'))
                    ->pluck('args')->all(), true),
            ];

            foreach ($secrets as $secret) {
                expect(implode("\n", $dumps))->not->toContain($secret);
            }
        });
    } finally {
        ini_set('zend.exception_ignore_args', (string) $original);
    }
})->with([
    'pem key with a wrong passphrase' => ['pem-encrypted', trim(C::PASSPHRASE)],
    'pkcs12 with a wrong passphrase'  => ['pkcs12', trim(C::PASSPHRASE)],
]);
