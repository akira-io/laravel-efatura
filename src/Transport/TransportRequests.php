<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Configuration\HttpClientConfig;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Support\Fiscal;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

final readonly class TransportRequests
{
    public function __construct(private Factory $http) {}

    public function for(HttpClientConfig $config, PackagedArchive|Environment $repository, string $accept = 'application/xml'): PendingRequest
    {
        $environment = $repository instanceof PackagedArchive ? $repository->repository : $repository;

        return $this->plain($config)
            ->baseUrl($config->baseUrl ?? throw DefinitionException::transportEndpoint())
            ->accept($accept)
            ->withHeaders([Fiscal::REPOSITORY_HEADER => (string) $environment->code()]);
    }

    public function plain(HttpClientConfig $config): PendingRequest
    {
        return $this->http->createPendingRequest()
            ->setHandler(new BoundedCurlHandler)
            ->timeout($config->timeoutSeconds)
            ->connectTimeout($config->connectTimeoutSeconds)
            ->withoutRedirecting()
            ->withOptions(['verify' => $config->verify()]);
    }
}
