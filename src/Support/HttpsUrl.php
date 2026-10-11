<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Illuminate\Support\Str;
use SensitiveParameter;

use const FILTER_VALIDATE_URL;

final readonly class HttpsUrl
{
    /**
     * @return array{host: string, port: int|null}|null
     */
    public static function origin(#[SensitiveParameter] mixed $url, bool $allowQuery = false): ?array
    {
        $parts = \is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false ? parse_url($url) : false;

        if (! \is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? '') === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || (! $allowQuery && isset($parts['query']))) {
            return null;
        }

        return ['host' => Str::lower($parts['host']), 'port' => $parts['port'] ?? null];
    }

    /**
     * @phpstan-assert-if-true string $url
     */
    public static function isSafe(#[SensitiveParameter] mixed $url, bool $allowQuery = false): bool
    {
        return self::origin($url, $allowQuery) !== null;
    }
}
