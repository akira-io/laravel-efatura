<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Signing\OpenSslCertificateLoader;
use Akira\Efatura\Signing\SignedPropertiesWriter;
use Akira\Efatura\Signing\SigningCredentials;
use Akira\Efatura\Signing\XadesBesXmlSigner;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;

beforeEach(function (): void {
    $this->credentials = C::credentials();
    $this->summary     = [
        'issuerName'   => C::CA_NAME,
        'serialNumber' => '1715004',
        'validFrom'    => '2024-12-31T23:00:00-01:00',
        'validTo'      => '2039-12-31T23:00:00-01:00',
    ];
});

it('shows only the issuer, the serial number and the validity when dumped', function (): void {
    ob_start();
    var_dump($this->credentials);
    $dump = ob_get_clean();

    expect($this->credentials->__debugInfo())->toBe($this->summary)
        ->and($dump)->not->toContain('privateKey')
        ->and($dump)->not->toContain('certificateDer')
        ->and(print_r($this->credentials, true))->not->toContain('privateKey');
});

it('encodes only the same summary as json', function (): void {
    expect(json_decode((string) json_encode($this->credentials), true))->toBe($this->summary);
});

it('refuses to be serialized or unserialized', function (): void {
    $payload = 'O:' . mb_strlen(SigningCredentials::class) . ':"' . SigningCredentials::class . '":0:{}';

    expect(fn (): string => serialize($this->credentials))->toThrow(DefinitionException::class, 'Signing credentials hold a private key and cannot be serialized.')
        ->and(fn (): mixed => unserialize($payload))->toThrow(DefinitionException::class, 'Signing credentials hold a private key and cannot be serialized.');
});

it('is a sensitive parameter of every signing method that takes it', function (): void {
    $secretTypes = [SigningCredentials::class, OpenSSLAsymmetricKey::class];
    $parameters  = collect([OpenSslCertificateLoader::class, SignedPropertiesWriter::class, XadesBesXmlSigner::class, SigningCredentials::class])
        ->flatMap(fn (string $class): array => new ReflectionClass($class)->getMethods())
        ->flatMap(fn (ReflectionMethod $method): array => $method->getParameters())
        ->filter(fn (ReflectionParameter $parameter): bool => in_array((string) $parameter->getType(), $secretTypes, true));

    expect($parameters)->not->toBeEmpty()
        ->each(fn ($parameter) => $parameter->getAttributes(SensitiveParameter::class)->not->toBeEmpty());
});
