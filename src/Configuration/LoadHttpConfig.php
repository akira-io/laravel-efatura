<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\ReadsConfigurationValues;
use Akira\Efatura\Exceptions\ConfigurationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use SensitiveParameter;

final readonly class LoadHttpConfig
{
    use ReadsConfigurationValues;

    public function __construct(#[SensitiveParameter] private Repository $repository) {}

    public function __invoke(): HttpConfig
    {
        $this->section('efatura.http');
        $defaults = $this->client('efatura.http', new HttpClientConfig(null, 30, 10, 2, 200, 5, true));

        return new HttpConfig(
            $defaults,
            $this->profile('middleware', $defaults, 'https://localhost:3443/v1'),
            $this->profile('platform', $defaults, 'https://services.efatura.cv/v1'),
            $this->profile('bcv', $defaults),
            $this->profile('world_bank', $defaults),
        );
    }

    private function repository(): Repository
    {
        return $this->repository;
    }

    private function profile(string $name, HttpClientConfig $defaults, ?string $baseUrl = null): HttpClientConfig
    {
        $this->section('efatura.http.' . $name);

        return $this->client('efatura.http.' . $name, $defaults, $baseUrl);
    }

    private function client(string $path, #[SensitiveParameter] HttpClientConfig $defaults, #[SensitiveParameter] ?string $baseUrl = null): HttpClientConfig
    {
        $verifyTls = $this->boolean($path . '.verify_tls', $defaults->verifyTls);

        return new HttpClientConfig(
            $this->url($path . '.base_url', $baseUrl),
            $this->integer($path . '.timeout_seconds', $defaults->timeoutSeconds),
            $this->integer($path . '.connect_timeout_seconds', $defaults->connectTimeoutSeconds),
            $this->integer($path . '.retries', $defaults->retries, 0),
            $this->integer($path . '.retry_delay_milliseconds', $defaults->retryDelayMilliseconds, 0),
            $this->integer($path . '.concurrency', $defaults->concurrency),
            $verifyTls,
            $this->caBundle($path . '.ca_bundle_path', $defaults->caBundlePath, $verifyTls),
        );
    }

    private function caBundle(string $path, #[SensitiveParameter] ?string $inherited, bool $verifyTls): ?string
    {
        $bundle = $this->string($path) ?? $inherited;
        if ($bundle === null) {
            return null;
        }

        if (! Str::startsWith($bundle, '/') || preg_match('/[\x00-\x1f]/', $bundle) === 1) {
            throw new ConfigurationException('configuration.unsafe_path', $path);
        }

        if (! is_file($bundle) || ! is_readable($bundle)) {
            throw new ConfigurationException('configuration.unreadable_path', $path);
        }

        if (! $verifyTls) {
            throw new ConfigurationException('configuration.conflicting_tls', $path);
        }

        return $bundle;
    }
}
