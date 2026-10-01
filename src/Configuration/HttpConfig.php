<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class HttpConfig
{
    public function __construct(
        public HttpClientConfig $defaults,
        public HttpClientConfig $middleware,
        public HttpClientConfig $platform,
        public HttpClientConfig $bcv,
        public HttpClientConfig $worldBank,
    ) {}
}
