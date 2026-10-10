<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Support\Fiscal;

final readonly class MiddlewareTransport implements Transport
{
    public function __construct(private TransportExchange $exchange, private EfaturaConfig $config) {}

    public function submit(PackagedArchive $archive): SubmissionResult
    {
        $response = $this->exchange->post($this->config->http->middleware, $archive, $this->credentials($archive));

        return $this->exchange->submission($archive, $response);
    }

    public function lookupDocument(string $iud): DocumentLookup
    {
        $response = $this->exchange->fetch($this->config->http->middleware, $iud, $this->credentials($iud));

        return $this->exchange->lookup($iud, $response);
    }

    /**
     * @return array<string, string>
     */
    private function credentials(PackagedArchive|string $subject): array
    {
        $key = $this->config->transmitter->middlewareKey
            ?? throw TransportException::because(TransportFailure::MissingCredentials, $subject, field: 'efatura.transmitter.middleware_key');

        return [Fiscal::TRANSMITTER_KEY_HEADER => $key];
    }
}
