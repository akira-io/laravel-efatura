<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\ReadsConfigurationValues;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\TransportDriver;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Support\Fiscal;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use SensitiveParameter;

final readonly class LoadEfaturaConfig
{
    use ReadsConfigurationValues;

    private const array SECTIONS = [
        'efatura',
        'efatura.transmitter',
        'efatura.transmitter.oauth',
        'efatura.software',
        'efatura.certificates',
        'efatura.storage',
        'efatura.cache',
        'efatura.database',
        'efatura.queue',
    ];

    public function __construct(#[SensitiveParameter] private Repository $repository) {}

    public function __invoke(): EfaturaConfig
    {
        foreach (self::SECTIONS as $path) {
            $this->section($path);
        }

        $queueConnection = $this->inherit('efatura.queue.connection', 'queue.default');

        return new EfaturaConfig(
            emitter: $this->emitter(),
            transmitter: new TransmitterConfig(
                $this->taxId('efatura.transmitter.tax_id'),
                $this->string('efatura.transmitter.name'),
                $this->middlewareKey(),
                new OAuthConfig(
                    $this->string('efatura.transmitter.oauth.client_id'),
                    $this->string('efatura.transmitter.oauth.client_secret', secret: true),
                    $this->url('efatura.transmitter.oauth.issuer_url', Fiscal::OAUTH_ISSUER_URL)
                        ?? throw new ConfigurationException('configuration.invalid_type', 'efatura.transmitter.oauth.issuer_url'),
                ),
            ),
            software: new SoftwareConfig(
                $this->string('efatura.software.code'),
                $this->string('efatura.software.name'),
                $this->string('efatura.software.version'),
            ),
            environment: new EnvironmentConfig($this->environment($this->repository->get('efatura.environment', Environment::Test))),
            certificates: new CertificateConfig(
                $this->inherit('efatura.certificates.disk', 'filesystems.default'),
                $this->relativePath('efatura.certificates.certificate_path'),
                $this->relativePath('efatura.certificates.private_key_path'),
                $this->string('efatura.certificates.passphrase', secret: true),
                $this->relativePath('efatura.certificates.ca_bundle_path'),
            ),
            storage: new StorageConfig(
                $this->inherit('efatura.storage.disk', 'filesystems.default'),
                $this->relativePath('efatura.storage.path', 'efatura')
                    ?? throw new ConfigurationException('configuration.invalid_type', 'efatura.storage.path'),
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
            http: (new LoadHttpConfig($this->repository))(),
            driver: $this->driver(),
        );
    }

    private function repository(): Repository
    {
        return $this->repository;
    }

    private function driver(): TransportDriver
    {
        $value = $this->repository->get('efatura.driver', TransportDriver::Middleware);
        if ($value instanceof TransportDriver) {
            return $value;
        }

        $name = \is_string($value) ? TransportDriver::tryFrom(Str::lower(Str::trim($value, " \n\r\t\v\0"))) : null;

        return $name ?? throw new ConfigurationException('configuration.invalid_driver', 'efatura.driver');
    }

    private function middlewareKey(): ?string
    {
        $key = $this->string('efatura.transmitter.middleware_key', secret: true);
        if ($key !== null && Str::length($key) !== Fiscal::TRANSMITTER_KEY_LENGTH) {
            throw new ConfigurationException('configuration.invalid_length', 'efatura.transmitter.middleware_key');
        }

        return $key;
    }

    private function inherit(string $path, string $hostPath): string
    {
        return $this->string($path) ?? $this->requiredString($hostPath);
    }

    private function led(string $path): ?int
    {
        $value = $this->repository->get($path);

        return $this->validatedLed(\is_int($value) ? (string) $value : $this->string($path), $path);
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
            $this->led('efatura.emitter.led'),
            new AddressConfig(
                $this->string('efatura.emitter.address.country_code'),
                $this->string('efatura.emitter.address.region'),
                $this->string('efatura.emitter.address.city'),
                $this->string('efatura.emitter.address.street'),
                $this->string('efatura.emitter.address.postal_code'),
                $this->string('efatura.emitter.address.address_detail'),
                $this->string('efatura.emitter.address.address_code'),
                $this->string('efatura.emitter.address.state'),
                $this->string('efatura.emitter.address.street_detail'),
                $this->string('efatura.emitter.address.building_name'),
                $this->string('efatura.emitter.address.building_number'),
                $this->string('efatura.emitter.address.building_floor'),
            ),
            new ContactsConfig(
                $this->string('efatura.emitter.contacts.email'),
                $this->string('efatura.emitter.contacts.telephone'),
                $this->string('efatura.emitter.contacts.mobile'),
                $this->string('efatura.emitter.contacts.telefax'),
                $this->string('efatura.emitter.contacts.website'),
            ),
        );
        foreach ([$emitter->taxId, $emitter->name, $emitter->led, ...get_object_vars($emitter->address), ...get_object_vars($emitter->contacts)] as $field) {
            if ($field !== null) {
                return $emitter;
            }
        }

        return null;
    }
}
