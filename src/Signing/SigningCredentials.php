<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigInteger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use JsonSerializable;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use SensitiveParameter;

final readonly class SigningCredentials implements JsonSerializable
{
    public function __construct(
        public OpenSSLCertificate $certificate,
        #[SensitiveParameter]
        public OpenSSLAsymmetricKey $privateKey,
        public string $certificateDer,
        public string $issuerName,
        public string $serialNumber,
        public CarbonImmutable $validFrom,
        public CarbonImmutable $validTo,
    ) {}

    /**
     * @return array{issuerName: string, serialNumber: string, validFrom: string, validTo: string}
     */
    public function __debugInfo(): array
    {
        return [
            'issuerName'   => $this->issuerName,
            'serialNumber' => $this->serialNumber,
            'validFrom'    => $this->validFrom->toIso8601String(),
            'validTo'      => $this->validTo->toIso8601String(),
        ];
    }

    /**
     * @return array<never>
     */
    public function __serialize(): array
    {
        throw DefinitionException::credentialsSerialization();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw DefinitionException::credentialsSerialization();
    }

    public function certificateDigest(): string
    {
        return base64_encode(hash('sha256', $this->certificateDer, true));
    }

    public static function fromCertificate(OpenSSLCertificate $certificate, #[SensitiveParameter] OpenSSLAsymmetricKey $privateKey): self
    {
        $info = openssl_x509_parse($certificate) ?: [];
        openssl_x509_export($certificate, $pem);
        $der = (string) base64_decode(Str::of(\is_string($pem) ? $pem : '')->betweenFirst('-----BEGIN CERTIFICATE-----', '-----END')->squish()->value(), true);

        return new self(
            $certificate,
            $privateKey,
            $der,
            DistinguishedName::issuerOf($der),
            BigInteger::fromBase(\is_string($info['serialNumberHex'] ?? null) ? $info['serialNumberHex'] : '0', 16)->toBase(10),
            self::moment($info['validFrom_time_t'] ?? null),
            self::moment($info['validTo_time_t'] ?? null),
        );
    }

    /**
     * @return array{issuerName: string, serialNumber: string, validFrom: string, validTo: string}
     */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    private static function moment(mixed $timestamp): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp(\is_int($timestamp) ? $timestamp : 0, Fiscal::TIMEZONE);
    }
}
