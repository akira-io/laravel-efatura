<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Exceptions\ConfigurationException;
use Illuminate\Config\Repository;

it('rejects invalid configuration with stable safe field errors', function (string $field, mixed $configured, string $errorCode): void {
    $repository = resolve('config');
    $repository->set($field, $configured);

    try {
        (new LoadEfaturaConfig($repository))();
        test()->fail('Invalid configuration was accepted.');
    } catch (ConfigurationException $configurationException) {
        expect($configurationException->errorCode)->toBe($errorCode)
            ->and($configurationException->field)->toBe($field)
            ->and($configurationException->getMessage())->toBe($errorCode . ': ' . $field);
    }
})->with([
    ['efatura.environment', 'UNKNOWN', 'configuration.invalid_environment'],
    ['efatura.environment', 99, 'configuration.invalid_environment'],
    ['efatura.environment', null, 'configuration.invalid_environment'],
    ['efatura.environment', false, 'configuration.invalid_environment'],
    ['efatura.http.timeout_seconds', 0, 'configuration.invalid_integer'],
    ['efatura.http.connect_timeout_seconds', -1, 'configuration.invalid_integer'],
    ['efatura.http.retries', -1, 'configuration.invalid_integer'],
    ['efatura.http.concurrency', '9999999999999999999999999', 'configuration.invalid_integer'],
    ['efatura.http.timeout_seconds', true, 'configuration.invalid_integer'],
    ['efatura.http.timeout_seconds', 1.5, 'configuration.invalid_integer'],
    ['efatura.http.middleware.timeout_seconds', 'bad', 'configuration.invalid_integer'],
    ['efatura.http.verify_tls', 'false', 'configuration.invalid_type'],
    ['efatura.http.platform.base_url', 'http://example.test', 'configuration.unsafe_url'],
    ['efatura.http.platform.base_url', 'https://user:secret@example.test', 'configuration.unsafe_url'],
    ['efatura.http.platform.base_url', 'file:///etc/passwd', 'configuration.unsafe_url'],
    ['efatura.http.platform.base_url', 'https://example.test?secret=redact', 'configuration.unsafe_url'],
    ['efatura.http.platform.base_url', 'https://example.test/#token', 'configuration.unsafe_url'],
    ['efatura.transmitter.tax_id', '123', 'configuration.invalid_tax_id'],
    ['efatura.emitter.tax_id', 'abcdefghi', 'configuration.invalid_tax_id'],
    ['efatura.emitter.tax_id', 100200300, 'configuration.invalid_type'],
    ['efatura.storage.disk', '', 'configuration.empty_string'],
    ['efatura.cache.store', ' ', 'configuration.empty_string'],
    ['efatura.storage.path', '../private', 'configuration.unsafe_path'],
    ['efatura.storage.path', '/absolute', 'configuration.unsafe_path'],
    ['efatura.storage.path', null, 'configuration.invalid_type'],
    ['efatura.cache.prefix', null, 'configuration.invalid_type'],
    ['efatura.certificates.private_key_path', 'certs/../secret.pem', 'configuration.unsafe_path'],
    ['efatura.certificates.certificate_path', 'C:\secret.pem', 'configuration.unsafe_path'],
    ['efatura.storage.path', "fiscal\0documents", 'configuration.unsafe_path'],
    ['efatura.database.sequences_table', 'table; DROP TABLE users', 'configuration.invalid_identifier'],
    ['efatura.software.name', ['invalid'], 'configuration.invalid_type'],
    ['efatura.transmitter.oauth.client_secret', ['sensitive'], 'configuration.invalid_type'],
    ['efatura.storage', 'local', 'configuration.invalid_type'],
    ['efatura.http.middleware', false, 'configuration.invalid_type'],
    ['efatura.emitter.contacts', 'email', 'configuration.invalid_type'],
    ['efatura', false, 'configuration.invalid_type'],
    ['efatura', ['list-entry'], 'configuration.invalid_type'],
]);

it('validates host defaults only when inherited', function (): void {
    $repository = new Repository(['filesystems' => ['default' => false], 'queue' => ['default' => 'sync']]);

    expect(fn (): EfaturaConfig => (new LoadEfaturaConfig($repository))())
        ->toThrow(ConfigurationException::class, 'configuration.invalid_type: filesystems.default');

    $repository->set('efatura', [
        'storage' => ['disk' => 'local'], 'certificates' => ['disk' => 'private'],
        'cache'   => ['store' => 'array'], 'database' => ['connection' => 'sqlite'],
        'queue'   => ['connection' => 'sync'],
    ]);
    expect((new LoadEfaturaConfig($repository))()->storage->disk)->toBe('local');
});

it('rejects missing required host defaults with their original field path', function (): void {
    expect(fn (): EfaturaConfig => (new LoadEfaturaConfig(new Repository))())
        ->toThrow(ConfigurationException::class, 'configuration.invalid_type: queue.default');
});
