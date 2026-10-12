<?php

declare(strict_types=1);

namespace Akira\Efatura\Readiness;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\LoadRoutesConfig;
use Akira\Efatura\Contracts\CertificateLoader;
use Akira\Efatura\Contracts\RefreshTokenRepository;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\ReadinessCheck;
use Akira\Efatura\Enums\TransportDriver;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Psr\Clock\ClockInterface;

final readonly class LocalChecks
{
    private const string MISSING = 'configuration.missing';

    private const string CA_BUNDLE = 'efatura.certificates.ca_bundle_path';

    public function __construct(
        private LoadRoutesConfig $routes,
        private CertificateLoader $certificates,
        private ConnectionResolverInterface $connections,
        private Container $container,
        private ClockInterface $clock,
    ) {}

    /**
     * @return list<ReadinessResult>
     */
    public function configuration(EfaturaConfig $config): array
    {
        $required = [
            'efatura.transmitter.tax_id' => $config->transmitter->taxId,
            'efatura.software.code'      => $config->software->code,
            'efatura.software.name'      => $config->software->name,
            'efatura.software.version'   => $config->software->version,
            ...$this->driverSettings($config),
        ];
        $missing = array_keys(array_filter($required, static fn (?string $value): bool => $value === null));
        if ($missing !== []) {
            return array_map(self::missing(...), $missing);
        }

        $credentials = $config->driver === TransportDriver::Platform ? $this->refreshToken((string) $config->emitter?->taxId) : null;

        return [$credentials ?? ReadinessResult::passed(ReadinessCheck::Configuration, 'readiness.configured', ['driver' => $config->driver->value])];
    }

    /**
     * @return list<ReadinessResult>
     */
    public function routes(): array
    {
        try {
            $this->routes->inspect();
        } catch (ConfigurationException $configurationException) {
            return [ReadinessResult::warning(ReadinessCheck::Configuration, $configurationException->errorCode, $configurationException->field)];
        }

        return [];
    }

    /**
     * @return list<ReadinessResult>
     */
    public function certificate(EfaturaConfig $config): array
    {
        $credentials = $this->certificates->load($config->certificates);
        $now         = CarbonImmutable::instance($this->clock->now());
        $daysLeft    = (int) floor($now->diffInDays($credentials->validTo));
        $warnings    = [];
        if ($daysLeft < Fiscal::CERTIFICATE_EXPIRY_WARNING_DAYS) {
            $warnings[] = ReadinessResult::warning(
                ReadinessCheck::Certificate,
                'readiness.certificate_expiring',
                'efatura.certificates.certificate_path',
                ['days_left' => $daysLeft],
            );
        }

        if ($config->environment->environment === Environment::Production && $config->certificates->caBundlePath === null) {
            $warnings[] = ReadinessResult::warning(ReadinessCheck::Certificate, 'readiness.certificate_chain_unverified', self::CA_BUNDLE);
        }

        return $warnings !== [] ? $warnings : [ReadinessResult::passed(ReadinessCheck::Certificate, 'readiness.certificate_valid', ['days_left' => $daysLeft])];
    }

    /**
     * @return list<ReadinessResult>
     */
    public function sequences(EfaturaConfig $config): array
    {
        $connection = $this->connections->connection($config->database->connection);
        $present    = $connection instanceof Connection && $connection->getSchemaBuilder()->hasTable($config->database->sequencesTable);

        return [$present
            ? ReadinessResult::passed(ReadinessCheck::Sequences, 'readiness.sequences_table_present')
            : ReadinessResult::failed(ReadinessCheck::Sequences, 'readiness.sequences_table_missing', 'efatura.database.sequences_table')];
    }

    /**
     * @return list<ReadinessResult>
     */
    public function environment(EfaturaConfig $config): array
    {
        $profile = $config->driver->value;
        $client  = $config->driver === TransportDriver::Platform ? $config->http->platform : $config->http->middleware;
        $context = ['environment' => $config->environment->environment->value, 'driver' => $profile];
        $field   = 'efatura.http.' . $profile . '.verify_tls';

        return [$client->verifyTls
            ? ReadinessResult::passed(ReadinessCheck::Environment, 'readiness.environment_configured', $context)
            : ReadinessResult::warning(ReadinessCheck::Environment, 'readiness.tls_verification_disabled', $field, $context)];
    }

    /**
     * @return array<string, string|null>
     */
    private function driverSettings(EfaturaConfig $config): array
    {
        return match ($config->driver) {
            TransportDriver::Middleware => ['efatura.transmitter.middleware_key' => $config->transmitter->middlewareKey === null ? null : 'present'],
            TransportDriver::Platform   => [
                'efatura.emitter.tax_id'                  => $config->emitter?->taxId,
                'efatura.transmitter.oauth.client_id'     => $config->transmitter->oauth->clientId,
                'efatura.transmitter.oauth.client_secret' => $config->transmitter->oauth->clientSecret === null ? null : 'present',
            ],
        };
    }

    private function refreshToken(string $emitterTaxId): ?ReadinessResult
    {
        if (! $this->container->bound(RefreshTokenRepository::class)) {
            return ReadinessResult::failed(ReadinessCheck::Configuration, TransportFailure::MissingCredentials->value, 'efatura.transmitter.oauth');
        }

        $repository = $this->container->make(RefreshTokenRepository::class);

        return $repository->find($emitterTaxId) === null
            ? ReadinessResult::failed(ReadinessCheck::Configuration, 'oauth.refresh_token_missing', 'efatura.emitter.tax_id')
            : null;
    }

    private static function missing(string $field): ReadinessResult
    {
        return ReadinessResult::failed(ReadinessCheck::Configuration, self::MISSING, $field);
    }
}
