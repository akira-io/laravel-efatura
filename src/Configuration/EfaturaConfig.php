<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class EfaturaConfig
{
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
    ) {}
}
