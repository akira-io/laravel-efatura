<?php

declare(strict_types=1);

namespace Akira\Efatura\Readiness;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Enums\OAuthScope;
use Akira\Efatura\Enums\ReadinessCheck;
use Akira\Efatura\Enums\TransportDriver;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Transport\FailureClassifier;
use Akira\Efatura\Transport\ReadRetries;
use Akira\Efatura\Transport\TransportRequests;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class RemoteChecks
{
    private const string HTTP_DATE = 'D, d M Y H:i:s \G\M\T';

    public function __construct(
        private TransportRequests $requests,
        private ReadRetries $retries,
        private FailureClassifier $failures,
        private Container $container,
        private ClockInterface $clock,
    ) {}

    /**
     * @return list<ReadinessResult>
     */
    public function run(EfaturaConfig $config): array
    {
        $client = $config->driver === TransportDriver::Platform ? $config->http->platform : $config->http->middleware;
        $base   = $client->baseUrl ?? throw DefinitionException::transportEndpoint();

        try {
            $response = $this->retries->apply($this->requests->plain($client)->baseUrl($base), $client)->get('');
        } catch (ConnectionException $connectionException) {
            return [
                ReadinessResult::failure(ReadinessCheck::Endpoint, $this->failures->classify($connectionException, null)),
                ReadinessResult::skipped(ReadinessCheck::Clock, true),
                $this->credentials($config),
            ];
        }

        return [
            ReadinessResult::passed(ReadinessCheck::Endpoint, 'readiness.endpoint_reachable', ['status' => $response->status()]),
            $this->clock($response),
            $this->credentials($config),
        ];
    }

    private function clock(Response $response): ReadinessResult
    {
        try {
            $remote = CarbonImmutable::createFromFormat(self::HTTP_DATE, $response->header('Date'), 'UTC');
        } catch (InvalidFormatException) {
            $remote = null;
        }

        if (! $remote instanceof CarbonImmutable) {
            return ReadinessResult::warning(ReadinessCheck::Clock, 'readiness.clock_unverified');
        }

        $skew    = abs($remote->getTimestamp() - $this->clock->now()->getTimestamp());
        $context = ['skew_seconds' => $skew];

        return match (true) {
            $skew > Fiscal::ONLINE_ISSUE_LEAD_SECONDS  => ReadinessResult::failed(ReadinessCheck::Clock, 'readiness.clock_skew', null, $context),
            $skew > Fiscal::CLOCK_SKEW_WARNING_SECONDS => ReadinessResult::warning(ReadinessCheck::Clock, 'readiness.clock_skew', null, $context),
            default                                    => ReadinessResult::passed(ReadinessCheck::Clock, 'readiness.clock_synchronized', $context),
        };
    }

    private function credentials(EfaturaConfig $config): ReadinessResult
    {
        if ($config->driver === TransportDriver::Middleware) {
            return ReadinessResult::skipped(ReadinessCheck::Credentials, false);
        }

        if (! $this->container->bound(AccessTokenProvider::class)) {
            return ReadinessResult::failed(ReadinessCheck::Credentials, TransportFailure::MissingCredentials->value, 'efatura.transmitter.oauth');
        }

        try {
            $this->container->make(AccessTokenProvider::class)->token((string) $config->emitter?->taxId, [OAuthScope::DfeCreate]);
        } catch (Throwable $throwable) {
            return ReadinessResult::failure(ReadinessCheck::Credentials, $throwable);
        }

        return ReadinessResult::passed(ReadinessCheck::Credentials, 'readiness.token_obtained');
    }
}
