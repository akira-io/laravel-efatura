<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Exceptions\ConfigurationException;
use Illuminate\Config\Repository;

it('rejects invalid configuration with stable safe field errors', function (string $field, mixed $configured, string $errorCode): void {
    $repository = resolve('config');
    $repository->set($field, $configured);

    $loader = new LoadEfaturaConfig($repository);

    expect(fn (): EfaturaConfig => $loader())
        ->toThrow(function (ConfigurationException $configurationException) use ($field, $errorCode): void {
            expect($configurationException->errorCode)->toBe($errorCode)
                ->and($configurationException->field)->toBe($field)
                ->and($configurationException->getMessage())->toBe($errorCode . ': ' . $field);
        });
})->with([
    ['efatura.environment', 'UNKNOWN', 'configuration.invalid_environment'],
    ['efatura.environment', 99, 'configuration.invalid_environment'],
    ['efatura.environment', null, 'configuration.invalid_environment'],
    ['efatura.environment', false, 'configuration.invalid_environment'],
    ['efatura.environment', "te\u{017F}t", 'configuration.invalid_environment'],
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
    ['efatura.emitter.tax_id', '012345678', 'configuration.invalid_tax_id'],
    ['efatura.transmitter.tax_id', '012345678', 'configuration.invalid_tax_id'],
    ['efatura.emitter.led', '1e2', 'configuration.invalid_led'],
    ['efatura.emitter.led', '1.0', 'configuration.invalid_led'],
    ['efatura.emitter.led', 'abc', 'configuration.invalid_led'],
    ['efatura.emitter.led', '0', 'configuration.invalid_led'],
    ['efatura.emitter.led', '01', 'configuration.invalid_led'],
    ['efatura.emitter.led', '100000', 'configuration.invalid_led'],
    ['efatura.emitter.led', '+5', 'configuration.invalid_led'],
    ['efatura.emitter.led', 0, 'configuration.invalid_led'],
    ['efatura.emitter.led', -5, 'configuration.invalid_led'],
    ['efatura.emitter.led', 100000, 'configuration.invalid_led'],
    ['efatura.emitter.led', 5.0, 'configuration.invalid_type'],
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
    ['efatura.transmitter.oauth', 'invalid', 'configuration.invalid_type'],
    ['efatura.http.platform', 'invalid', 'configuration.invalid_type'],
    ['efatura.software', 'invalid', 'configuration.invalid_type'],
    ['efatura.software', ['name' => 'valid', 0 => 'invalid'], 'configuration.invalid_type'],
    ['efatura.transmitter.oauth.client_secret', ['sensitive'], 'configuration.invalid_type'],
    ['efatura.storage', 'local', 'configuration.invalid_type'],
    ['efatura.http.middleware', false, 'configuration.invalid_type'],
    ['efatura.emitter.contacts', 'email', 'configuration.invalid_type'],
    ['efatura', false, 'configuration.invalid_type'],
    ['efatura', ['list-entry'], 'configuration.invalid_type'],
]);

it('rejects an invalid inherited host default', function (): void {
    $loader = new LoadEfaturaConfig(new Repository(['filesystems' => ['default' => false], 'queue' => ['default' => 'sync']]));

    expect(fn (): EfaturaConfig => $loader())
        ->toThrow(ConfigurationException::class, 'configuration.invalid_type: filesystems.default');
});

it('ignores an invalid host default that every package setting overrides', function (): void {
    $repository = new Repository(['filesystems' => ['default' => false], 'queue' => ['default' => 'sync']]);
    $repository->set('efatura', [
        'storage' => ['disk' => 'local'], 'certificates' => ['disk' => 'private'],
        'cache'   => ['store' => 'array'], 'database' => ['connection' => 'sqlite'],
        'queue'   => ['connection' => 'sync'],
    ]);

    expect((new LoadEfaturaConfig($repository))()->storage->disk)->toBe('local');
});

it('rejects missing required host defaults with their original field path', function (): void {
    $loader = new LoadEfaturaConfig(new Repository);

    expect(fn (): EfaturaConfig => $loader())
        ->toThrow(ConfigurationException::class, 'configuration.invalid_type: queue.default');
});

it('converts a configured LED to its integer header value', function (int|string $configured, int $led): void {
    $repository = resolve('config');
    $repository->set('efatura.emitter.led', $configured);

    expect((new LoadEfaturaConfig($repository))()->emitter->led)->toBe($led);
})->with([['1', 1], ['99999', 99999], [' 5 ', 5], [22, 22], [99999, 99999]]);
