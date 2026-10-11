<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Configuration\HttpClientConfig;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Transport\BoundedBody;
use Akira\Efatura\Transport\ReadRetries;
use Akira\Efatura\Transport\TransportRequests;
use Illuminate\Http\Client\ConnectionException;

final readonly class ExchangeRateClient
{
    public const int MAX_BODY_BYTES = 1_048_576;

    public function __construct(private TransportRequests $requests, private ReadRetries $retries, private BoundedBody $body) {}

    public function get(HttpClientConfig $profile, string $url, string $accept, string $source, int $maxBytes = self::MAX_BODY_BYTES): string
    {
        try {
            $response = $this->retries->apply($this->requests->plain($profile)->accept($accept), $profile)->get($url);
        } catch (ConnectionException) {
            throw ExchangeRateException::because(ExchangeRateFailure::ProviderUnavailable, ['source' => $source]);
        }

        if (! $response->successful()) {
            throw ExchangeRateException::because(ExchangeRateFailure::ProviderUnavailable, ['source' => $source, 'status' => $response->status()]);
        }

        try {
            return $this->body->read($response, $source, $maxBytes);
        } catch (TransportException) {
            throw ExchangeRateException::because(ExchangeRateFailure::ResponseInvalid, ['source' => $source]);
        }
    }
}
