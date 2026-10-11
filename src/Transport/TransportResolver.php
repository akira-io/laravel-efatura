<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportDriver;
use Akira\Efatura\OAuth\AccessTokenProviderResolver;
use Illuminate\Contracts\Container\Container;

final readonly class TransportResolver
{
    public function __construct(private Container $container, private EfaturaConfig $config, private AccessTokenProviderResolver $tokens) {}

    public function withConfig(EfaturaConfig $config): self
    {
        return new self($this->container, $config, $this->tokens);
    }

    public function driver(?TransportDriver $driver = null): Transport
    {
        return match ($driver ?? $this->config->driver) {
            TransportDriver::Middleware => $this->container->make(MiddlewareTransport::class, ['config' => $this->config]),
            TransportDriver::Platform   => $this->container->make(PlatformTransport::class, ['config' => $this->config, 'tokens' => $this->tokens->provider()]),
        };
    }
}
