<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\ValidatesConfigurationValues;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Exceptions\ConfigurationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use SensitiveParameter;

use const FILTER_VALIDATE_INT;

final readonly class LoadEfaturaConfig
{
    use ValidatesConfigurationValues;

    public function __construct(#[SensitiveParameter] private Repository $repository) {}

    public function __invoke(): EfaturaConfig
    {
        foreach (['efatura', 'efatura.transmitter', 'efatura.transmitter.oauth', 'efatura.software', 'efatura.certificates', 'efatura.storage', 'efatura.cache', 'efatura.database', 'efatura.queue'] as $path) {
            $this->section($path);
        }

        $queueConnection = $this->inherit('efatura.queue.connection', 'queue.default');

        return new EfaturaConfig(
            emitter: $this->emitter(),
            transmitter: new TransmitterConfig(
                $this->taxId('efatura.transmitter.tax_id'),
                $this->string('efatura.transmitter.name'),
                $this->string('efatura.transmitter.middleware_key', secret: true),
                new OAuthConfig($this->string('efatura.transmitter.oauth.client_id'), $this->string('efatura.transmitter.oauth.client_secret', secret: true)),
            ),
            software: new SoftwareConfig($this->string('efatura.software.code'), $this->string('efatura.software.name'), $this->string('efatura.software.version')),
            environment: new EnvironmentConfig($this->environment($this->repository->get('efatura.environment', Environment::TEST))),
            certificates: new CertificateConfig(
                $this->inherit('efatura.certificates.disk', 'filesystems.default'),
                $this->relativePath('efatura.certificates.certificate_path'),
                $this->relativePath('efatura.certificates.private_key_path'),
                $this->string('efatura.certificates.passphrase', secret: true),
            ),
            storage: new StorageConfig(
                $this->inherit('efatura.storage.disk', 'filesystems.default'),
                $this->relativePath('efatura.storage.path', 'efatura') ?? throw new ConfigurationException('configuration.invalid_type', 'efatura.storage.path'),
            ),
            cache: new CacheConfig(
                $this->inherit('efatura.cache.store', 'cache.default'),
                $this->requiredString('efatura.cache.prefix', 'efatura'),
                $this->integer('efatura.cache.exchange_rates_ttl_seconds', 3600),
            ),
            database: new DatabaseConfig(
                $this->inherit('efatura.database.connection', 'database.default'),
                $this->identifier('efatura.database.sequences_table', 'efatura_sequences'),
            ),
            queue: new QueueConfig(
                $queueConnection,
                $this->string('efatura.queue.queue') ?? $this->string('queue.connections.' . $queueConnection . '.queue'),
            ),
            http: $this->http(),
        );
    }

    private function section(string $path, bool $nullable = false): void
    {
        $section = $this->repository->get($path, []);
        if ($nullable && $section === null) {
            return;
        }

        if (! \is_array($section)) {
            throw new ConfigurationException('configuration.invalid_type', $path);
        }

        foreach (collect($section)->keys() as $key) {
            if (! \is_string($key)) {
                throw new ConfigurationException('configuration.invalid_type', $path);
            }
        }
    }

    private function string(string $path, #[SensitiveParameter] ?string $default = null, bool $secret = false): ?string
    {
        $value = $this->repository->get($path, $default);
        if ($value === null) {
            return null;
        }

        if (! \is_string($value)) {
            throw new ConfigurationException('configuration.invalid_type', $path);
        }

        $normalized = Str::trim($value, " \n\r\t\v\0");
        if ($normalized === '') {
            throw new ConfigurationException('configuration.empty_string', $path);
        }

        return $secret ? $value : $normalized;
    }

    private function requiredString(string $path, #[SensitiveParameter] ?string $default = null): string
    {
        return $this->string($path, $default) ?? throw new ConfigurationException('configuration.invalid_type', $path);
    }

    private function inherit(string $path, string $hostPath): string
    {
        return $this->string($path) ?? $this->requiredString($hostPath);
    }

    private function integer(string $path, int $default, int $minimum = 1): int
    {
        $value = $this->repository->get($path) ?? $default;
        if (\is_string($value) && ctype_digit($value)) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
        }

        if (! \is_int($value) || $value < $minimum) {
            throw new ConfigurationException('configuration.invalid_integer', $path);
        }

        return $value;
    }

    private function boolean(string $path, bool $default): bool
    {
        $value = $this->repository->get($path) ?? $default;
        if (! \is_bool($value)) {
            throw new ConfigurationException('configuration.invalid_type', $path);
        }

        return $value;
    }

    private function taxId(string $path): ?string
    {
        return $this->validatedTaxId($this->string($path), $path);
    }

    private function relativePath(string $path, #[SensitiveParameter] ?string $default = null): ?string
    {
        return $this->validatedRelativePath($this->string($path, $default), $path);
    }

    private function identifier(string $path, #[SensitiveParameter] string $default): string
    {
        return $this->validatedIdentifier($this->requiredString($path, $default), $path);
    }

    private function url(string $path, #[SensitiveParameter] ?string $default = null): ?string
    {
        return $this->validatedUrl($this->string($path, $default), $path);
    }

    private function emitter(): ?EmitterConfig
    {
        $this->section('efatura.emitter', nullable: true);
        if ($this->repository->get('efatura.emitter') === null) {
            return null;
        }

        $this->section('efatura.emitter.address');
        $this->section('efatura.emitter.contacts');
        $emitter = new EmitterConfig(
            $this->taxId('efatura.emitter.tax_id'),
            $this->string('efatura.emitter.name'),
            $this->string('efatura.emitter.led'),
            new AddressConfig(
                $this->string('efatura.emitter.address.country_code'),
                $this->string('efatura.emitter.address.region'),
                $this->string('efatura.emitter.address.city'),
                $this->string('efatura.emitter.address.street'),
                $this->string('efatura.emitter.address.postal_code'),
            ),
            new ContactsConfig(
                $this->string('efatura.emitter.contacts.email'),
                $this->string('efatura.emitter.contacts.telephone'),
                $this->string('efatura.emitter.contacts.mobile'),
            ),
        );
        foreach ([$emitter->taxId, $emitter->name, $emitter->led, ...get_object_vars($emitter->address), ...get_object_vars($emitter->contacts)] as $field) {
            if ($field !== null) {
                return $emitter;
            }
        }

        return null;
    }

    private function http(): HttpConfig
    {
        $this->section('efatura.http');
        $defaults = $this->httpClient('efatura.http', new HttpClientConfig(null, 30, 10, 2, 200, 5, true));
        $this->section('efatura.http.middleware');
        $middleware = $this->httpClient('efatura.http.middleware', $defaults, 'https://localhost:3443');
        $this->section('efatura.http.platform');
        $platform = $this->httpClient('efatura.http.platform', $defaults, 'https://services.efatura.cv/v1');
        $this->section('efatura.http.bcv');
        $bcv = $this->httpClient('efatura.http.bcv', $defaults);
        $this->section('efatura.http.world_bank');

        return new HttpConfig(
            $defaults,
            $middleware,
            $platform,
            $bcv,
            $this->httpClient('efatura.http.world_bank', $defaults),
        );
    }

    private function httpClient(string $path, #[SensitiveParameter] HttpClientConfig $defaults, #[SensitiveParameter] ?string $baseUrl = null): HttpClientConfig
    {
        return new HttpClientConfig(
            $this->url($path . '.base_url', $baseUrl),
            $this->integer($path . '.timeout_seconds', $defaults->timeoutSeconds),
            $this->integer($path . '.connect_timeout_seconds', $defaults->connectTimeoutSeconds),
            $this->integer($path . '.retries', $defaults->retries, 0),
            $this->integer($path . '.retry_delay_milliseconds', $defaults->retryDelayMilliseconds, 0),
            $this->integer($path . '.concurrency', $defaults->concurrency),
            $this->boolean($path . '.verify_tls', $defaults->verifyTls),
        );
    }
}
