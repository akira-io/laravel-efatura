<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\RefusesSerialization;
use Akira\Efatura\Enums\TransportDriver;

final readonly class EfaturaConfig
{
    use RefusesSerialization;

    public function __construct(
        public ?EmitterConfig $emitter,
        public TransmitterConfig $transmitter,
        public SoftwareConfig $software,
        public EnvironmentConfig $environment,
        public CertificateConfig $certificates,
        public StorageConfig $storage,
        public CacheConfig $cache,
        public DatabaseConfig $database,
        public QueueConfig $queue,
        public HttpConfig $http,
        public TransportDriver $driver = TransportDriver::Middleware,
        public ExchangeRatesConfig $exchangeRates = new ExchangeRatesConfig,
        public DfaConfig $dfa = new DfaConfig,
    ) {}
}
