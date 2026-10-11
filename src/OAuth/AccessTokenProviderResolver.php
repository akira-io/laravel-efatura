<?php

declare(strict_types=1);

namespace Akira\Efatura\OAuth;

use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\RefreshTokenRepository;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Illuminate\Contracts\Container\Container;

final readonly class AccessTokenProviderResolver
{
    public function __construct(private Container $container) {}

    public function provider(): AccessTokenProvider
    {
        if ($this->container->bound(AccessTokenProvider::class)) {
            return $this->container->make(AccessTokenProvider::class);
        }

        throw_unless(
            $this->container->bound(RefreshTokenRepository::class),
            TransportException::because(TransportFailure::MissingCredentials, field: 'efatura.transmitter.oauth'),
        );

        return $this->container->make(RefreshTokenAccessTokenProvider::class);
    }
}
