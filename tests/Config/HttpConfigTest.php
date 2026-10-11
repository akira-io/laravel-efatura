<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Tests\Support\ConfigFixtures;

beforeEach(function (): void {
    $this->bundle = (string) tempnam(sys_get_temp_dir(), 'efatura-ca-');
    file_put_contents($this->bundle, "-----BEGIN CERTIFICATE-----\n-----END CERTIFICATE-----\n");
});

afterEach(function (): void {
    if (is_file($this->bundle)) {
        unlink($this->bundle);
    }
});

it('verifies tls against the system store unless a client names a ca bundle', function (): void {
    $http = ConfigFixtures::load(['http' => ['middleware' => ['ca_bundle_path' => $this->bundle]]])->http;

    expect($http->middleware->caBundlePath)->toBe($this->bundle)
        ->and($http->middleware->verify())->toBe($this->bundle)
        ->and($http->platform->caBundlePath)->toBeNull()
        ->and($http->platform->verify())->toBeTrue();
});

it('reports disabled verification as the request verify option', function (): void {
    expect(ConfigFixtures::load(['http' => ['platform' => ['verify_tls' => false]]])->http->platform->verify())->toBeFalse();
});

it('inherits the global ca bundle in every client', function (): void {
    $http = ConfigFixtures::load(['http' => ['ca_bundle_path' => $this->bundle]])->http;

    expect($http->defaults->verify())->toBe($this->bundle)
        ->and($http->middleware->verify())->toBe($this->bundle)
        ->and($http->worldBank->verify())->toBe($this->bundle);
});

it('rejects a ca bundle that is not a readable absolute local file', function (Closure $configure, string $field, string $errorCode): void {
    expect(fn (): EfaturaConfig => ConfigFixtures::load(['http' => $configure($this->bundle)]))
        ->toThrow(function (ConfigurationException $exception) use ($field, $errorCode): void {
            expect($exception->errorCode)->toBe($errorCode)
                ->and($exception->field)->toBe($field);
        });
})->with([
    'relative path'         => [fn (string $bundle): array => ['middleware' => ['ca_bundle_path' => 'certs/ca.pem']], 'efatura.http.middleware.ca_bundle_path', 'configuration.unsafe_path'],
    'control character'     => [fn (string $bundle): array => ['middleware' => ['ca_bundle_path' => $bundle . "\x01"]], 'efatura.http.middleware.ca_bundle_path', 'configuration.unsafe_path'],
    'missing file'          => [fn (string $bundle): array => ['platform' => ['ca_bundle_path' => $bundle . '.absent']], 'efatura.http.platform.ca_bundle_path', 'configuration.unreadable_path'],
    'directory'             => [fn (string $bundle): array => ['platform' => ['ca_bundle_path' => dirname($bundle)]], 'efatura.http.platform.ca_bundle_path', 'configuration.unreadable_path'],
    'verification disabled' => [fn (string $bundle): array => ['middleware' => ['ca_bundle_path' => $bundle, 'verify_tls' => false]], 'efatura.http.middleware.ca_bundle_path', 'configuration.conflicting_tls'],
    'inherited conflict'    => [fn (string $bundle): array => ['ca_bundle_path' => $bundle, 'bcv' => ['verify_tls' => false]], 'efatura.http.bcv.ca_bundle_path', 'configuration.conflicting_tls'],
    'not a string'          => [fn (string $bundle): array => ['middleware' => ['ca_bundle_path' => true]], 'efatura.http.middleware.ca_bundle_path', 'configuration.invalid_type'],
]);
