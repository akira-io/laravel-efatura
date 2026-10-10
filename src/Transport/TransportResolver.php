<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportDriver;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Illuminate\Contracts\Container\Container;

final readonly class TransportResolver
{
    public function __construct(private Container $container, private EfaturaConfig $config) {}

    public function withConfig(EfaturaConfig $config): self
    {
        return new self($this->container, $config);
    }

    public function driver(?TransportDriver $driver = null): Transport
    {
        return match ($driver ?? $this->config->driver) {
            TransportDriver::Middleware => $this->container->make(MiddlewareTransport::class, ['config' => $this->config]),
            TransportDriver::Platform   => $this->container->make(PlatformTransport::class, ['config' => $this->config, 'tokens' => $this->tokens()]),
        };
    }

    private function tokens(): AccessTokenProvider
    {
        throw_unless(
            $this->container->bound(AccessTokenProvider::class),
            TransportException::because(TransportFailure::MissingCredentials, field: 'efatura.transmitter.oauth'),
        );

        return $this->container->make(AccessTokenProvider::class);
    }
}
