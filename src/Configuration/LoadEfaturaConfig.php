<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Exceptions\ConfigurationException;
use Illuminate\Contracts\Config\Repository;

final readonly class LoadEfaturaConfig
{
    public function __construct(private Repository $repository) {}

    public function __invoke(): EfaturaConfig
    {
        $settings        = ConfigInput::from($this->repository->get('efatura', []), 'efatura');
        $transmitter     = $settings->section('transmitter');
        $oauth           = $transmitter->section('oauth');
        $software        = $settings->section('software');
        $certificates    = $settings->section('certificates');
        $storage         = $settings->section('storage');
        $cache           = $settings->section('cache');
        $database        = $settings->section('database');
        $queue           = $settings->section('queue');
        $queueConnection = $this->inherit($queue, 'connection', 'queue.default');

        return new EfaturaConfig(
            emitter: $this->emitter($settings->section('emitter', nullable: true)),
            transmitter: new TransmitterConfig(
                $transmitter->taxId('tax_id'),
                $transmitter->string('name'),
                $transmitter->string('middleware_key', secret: true),
                new OAuthConfig($oauth->string('client_id'), $oauth->string('client_secret', secret: true)),
            ),
            software: new SoftwareConfig($software->string('code'), $software->string('name'), $software->string('version')),
            environment: new EnvironmentConfig($this->environment($settings->read('environment', Environment::TEST))),
            certificates: new CertificateConfig(
                $this->inherit($certificates, 'disk', 'filesystems.default'),
                $certificates->relativePath('certificate_path'),
                $certificates->relativePath('private_key_path'),
                $certificates->string('passphrase', secret: true),
            ),
            storage: new StorageConfig(
                $this->inherit($storage, 'disk', 'filesystems.default'),
                $storage->relativePath('path', 'efatura') ?? throw new ConfigurationException('configuration.invalid_type', 'efatura.storage.path'),
            ),
            cache: new CacheConfig(
                $this->inherit($cache, 'store', 'cache.default'),
                $cache->requiredString('prefix', 'efatura'),
                $cache->integer('exchange_rates_ttl_seconds', 3600),
            ),
            database: new DatabaseConfig(
                $this->inherit($database, 'connection', 'database.default'),
                $database->identifier('sequences_table', 'efatura_sequences'),
            ),
            queue: new QueueConfig(
                $queueConnection,
                $queue->string('queue') ?? ConfigInput::normalizeString(
                    $this->repository->get('queue.connections.' . $queueConnection . '.queue'),
                    'queue.connections.' . $queueConnection . '.queue',
                ),
            ),
            http: $this->http($settings->section('http')),
        );
    }

    private function inherit(ConfigInput $settings, string $key, string $hostPath): string
    {
        return $settings->string($key) ?? ConfigInput::normalizeString($this->repository->get($hostPath), $hostPath)
            ?? throw new ConfigurationException('configuration.invalid_type', $hostPath);
    }

    private function environment(mixed $configured): Environment
    {
        if ($configured instanceof Environment) {
            return $configured;
        }

        if (\is_int($configured)) {
            return Environment::tryFrom($configured) ?? throw new ConfigurationException('configuration.invalid_environment', 'efatura.environment');
        }

        if (\is_string($configured)) {
            $normalized = strtoupper(trim($configured));

            return Environment::fromName($normalized) ?? match ($normalized) {
                '1'     => Environment::PRODUCTION,
                '2'     => Environment::HOMOLOGATION,
                '3'     => Environment::TEST,
                default => throw new ConfigurationException('configuration.invalid_environment', 'efatura.environment'),
            };
        }

        throw new ConfigurationException('configuration.invalid_environment', 'efatura.environment');
    }

    private function emitter(ConfigInput $settings): ?EmitterConfig
    {
        $address  = $settings->section('address');
        $contacts = $settings->section('contacts');
        $emitter  = new EmitterConfig(
            $settings->taxId('tax_id'),
            $settings->string('name'),
            $settings->string('led'),
            new AddressConfig(
                $address->string('country_code'),
                $address->string('region'),
                $address->string('city'),
                $address->string('street'),
                $address->string('postal_code'),
            ),
            new ContactsConfig($contacts->string('email'), $contacts->string('telephone'), $contacts->string('mobile')),
        );

        foreach ([$emitter->taxId, $emitter->name, $emitter->led, ...get_object_vars($emitter->address), ...get_object_vars($emitter->contacts)] as $field) {
            if ($field !== null) {
                return $emitter;
            }
        }

        return null;
    }

    private function http(ConfigInput $settings): HttpConfig
    {
        $defaults = $this->httpClient($settings, new HttpClientConfig(null, 30, 10, 2, 200, 5, true));

        return new HttpConfig(
            $defaults,
            $this->httpClient($settings->section('middleware'), $defaults, 'https://localhost:3443'),
            $this->httpClient($settings->section('platform'), $defaults, 'https://services.efatura.cv/v1'),
            $this->httpClient($settings->section('bcv'), $defaults),
            $this->httpClient($settings->section('world_bank'), $defaults),
        );
    }

    private function httpClient(ConfigInput $settings, HttpClientConfig $defaults, ?string $baseUrl = null): HttpClientConfig
    {
        return new HttpClientConfig(
            $settings->url('base_url', $baseUrl),
            $settings->integer('timeout_seconds', $defaults->timeoutSeconds),
            $settings->integer('connect_timeout_seconds', $defaults->connectTimeoutSeconds),
            $settings->integer('retries', $defaults->retries, 0),
            $settings->integer('retry_delay_milliseconds', $defaults->retryDelayMilliseconds, 0),
            $settings->integer('concurrency', $defaults->concurrency),
            $settings->boolean('verify_tls', $defaults->verifyTls),
        );
    }
}
