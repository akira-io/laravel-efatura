<?php

declare(strict_types=1);

namespace Akira\Efatura\Concerns;

use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Support\FiscalRules;
use Illuminate\Support\Str;
use SensitiveParameter;

use const FILTER_VALIDATE_URL;

trait ValidatesConfigurationValues
{
    private function validatedTaxId(#[SensitiveParameter] ?string $taxId, string $path): ?string
    {
        if ($taxId !== null && preg_match('/\A' . FiscalRules::CV_TAX_ID . '\z/', $taxId) !== 1) {
            throw new ConfigurationException('configuration.invalid_tax_id', $path);
        }

        return $taxId;
    }

    private function validatedLed(?string $led, string $path): ?int
    {
        if ($led === null) {
            return null;
        }

        if (preg_match('/\A' . FiscalRules::LED . '\z/', $led) !== 1) {
            throw new ConfigurationException('configuration.invalid_led', $path);
        }

        return (int) $led;
    }

    private function validatedRelativePath(#[SensitiveParameter] ?string $value, string $path): ?string
    {
        if ($value !== null && (preg_match('/[\x00-\x1f\\\:]/', $value) === 1 || Str::startsWith($value, '/')
            || Str::of($value)->explode('/')->intersect(['', '.', '..'])->isNotEmpty())) {
            throw new ConfigurationException('configuration.unsafe_path', $path);
        }

        return $value;
    }

    private function validatedIdentifier(#[SensitiveParameter] string $value, string $path): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $value) !== 1) {
            throw new ConfigurationException('configuration.invalid_identifier', $path);
        }

        return $value;
    }

    private function validatedUrl(#[SensitiveParameter] ?string $url, string $path): ?string
    {
        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || $parts === false || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new ConfigurationException('configuration.unsafe_url', $path);
        }

        return Str::rtrim($url, '/');
    }

    private function environment(#[SensitiveParameter] mixed $value): Environment
    {
        if ($value instanceof Environment) {
            return $value;
        }

        if (\is_int($value)) {
            return Environment::tryFrom($value) ?? throw new ConfigurationException('configuration.invalid_environment', 'efatura.environment');
        }

        if (\is_string($value)) {
            $normalized = Str::trim($value, " \n\r\t\v\0");

            return Environment::fromName($normalized) ?? match ($normalized) {
                '1'     => Environment::Production,
                '2'     => Environment::Homologation,
                '3'     => Environment::Test,
                default => throw new ConfigurationException('configuration.invalid_environment', 'efatura.environment'),
            };
        }

        throw new ConfigurationException('configuration.invalid_environment', 'efatura.environment');
    }
}
