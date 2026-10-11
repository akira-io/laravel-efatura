<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use SensitiveParameter;

final readonly class BoundedCurlHandler
{
    public function __construct(private CurlHandler $curl = new CurlHandler) {}

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(#[SensitiveParameter] RequestInterface $request, #[SensitiveParameter] array $options): PromiseInterface
    {
        return ($this->curl)($request, ['sink' => new BoundedSink] + $options);
    }
}
