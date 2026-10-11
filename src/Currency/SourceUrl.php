<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Exceptions\ExchangeRateException;
use SensitiveParameter;

final readonly class SourceUrl
{
    /**
     * @return array{host: string, port: int|null}
     */
    public static function parts(#[SensitiveParameter] string $url): array
    {
        $parts = parse_url($url);

        if (! \is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? '') === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw ExchangeRateException::because(ExchangeRateFailure::SourceRequired);
        }

        return ['host' => $parts['host'], 'port' => $parts['port'] ?? null];
    }

    public static function validated(#[SensitiveParameter] string $url): string
    {
        self::parts($url);

        return $url;
    }
}
