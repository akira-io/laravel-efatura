<?php

declare(strict_types=1);

namespace Akira\Efatura\OAuth;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Exceptions\OAuthException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Transport\BoundedBody;
use Akira\Efatura\Transport\ReadRetries;
use Akira\Efatura\Transport\TransportRequests;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use JsonException;
use SensitiveParameter;

use const JSON_THROW_ON_ERROR;
use const PHP_URL_HOST;
use const PHP_URL_PORT;

final readonly class OpenIdConfiguration
{
    private const int CACHE_SECONDS = 86_400;

    private const int JSON_DEPTH = 16;

    public function __construct(
        private TransportRequests $requests,
        private ReadRetries $retries,
        private BoundedBody $body,
        private CacheFactory $caches,
        private EfaturaConfig $config,
    ) {}

    public function tokenEndpoint(): string
    {
        $issuer = $this->config->transmitter->oauth->issuerUrl;
        $key    = $this->config->cache->prefix . ':oauth:discovery:' . hash('sha256', $issuer);

        return $this->caches->store($this->config->cache->store)->remember($key, self::CACHE_SECONDS, fn (): string => $this->discover($issuer));
    }

    private function discover(string $issuer): string
    {
        $profile = $this->config->http->platform;

        try {
            $response = $this->retries->apply($this->requests->plain($profile)->acceptJson(), $profile)
                ->get(Str::finish($issuer, '/') . '.well-known/openid-configuration');
        } catch (ConnectionException) {
            throw OAuthException::discoveryFailed();
        }

        $endpoint = $this->document($response)['token_endpoint'] ?? null;
        throw_unless(\is_string($endpoint) && self::sameOrigin($endpoint, $issuer), OAuthException::discoveryFailed($response->status()));

        return $endpoint;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function document(#[SensitiveParameter] Response $response): array
    {
        throw_unless($response->status() === 200, OAuthException::discoveryFailed($response->status()));

        try {
            $document = json_decode($this->body->read($response, 'oauth'), true, self::JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException|TransportException) {
            throw OAuthException::discoveryFailed($response->status());
        }

        return \is_array($document) ? $document : [];
    }

    private static function sameOrigin(string $endpoint, string $issuer): bool
    {
        $parts = parse_url($endpoint);

        return \is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ! isset($parts['user'])
            && Str::lower($parts['host'] ?? '') === Str::lower((string) parse_url($issuer, PHP_URL_HOST))
            && ($parts['port'] ?? null) === parse_url($issuer, PHP_URL_PORT);
    }
}
