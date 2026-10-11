<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Support\HttpsUrl;
use SensitiveParameter;

final readonly class SourceUrl
{
    /**
     * @return array{host: string, port: int|null}
     */
    public static function parts(#[SensitiveParameter] string $url): array
    {
        return HttpsUrl::origin($url, allowQuery: true) ?? throw ExchangeRateException::because(ExchangeRateFailure::SourceRequired);
    }

    public static function validated(#[SensitiveParameter] string $url): string
    {
        self::parts($url);

        return $url;
    }
}
