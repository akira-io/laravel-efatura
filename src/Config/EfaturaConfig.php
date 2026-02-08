<?php

declare(strict_types=1);

namespace Akira\Efatura\Config;

use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Illuminate\Config\Repository;

final readonly class EfaturaConfig
{
    public function __construct(
        private Repository $config,
    ) {}

    public function transmitterNif(): string
    {
        $value = $this->getString('efatura.transmitter.nif');

        if ($value === '') {
            $this->fail('transmitter.nif', __('efatura.config.transmitter_nif_required'));
        }

        return $value;
    }

    public function transmitterLedCode(): string
    {
        $value = $this->getString('efatura.transmitter.led');

        if ($value === '') {
            $this->fail('transmitter.led', __('efatura.config.transmitter_led_required'));
        }

        return $value;
    }

    public function softwareCode(): string
    {
        $value = $this->getString('efatura.software.code');

        if ($value === '') {
            $this->fail('software.code', __('efatura.config.software_code_required'));
        }

        return $value;
    }

    public function softwareName(): string
    {
        $value = $this->getString('efatura.software.name');

        if ($value === '') {
            $this->fail('software.name', __('efatura.config.software_name_required'));
        }

        return $value;
    }

    public function softwareVersion(): string
    {
        $value = $this->getString('efatura.software.version');

        if ($value === '') {
            $this->fail('software.version', __('efatura.config.software_version_required'));
        }

        return $value;
    }

    public function middlewareBaseUrl(): string
    {
        $value = $this->getString('efatura.middleware.base_url');

        if ($value === '') {
            $this->fail('middleware.base_url', __('efatura.config.middleware_base_url_required'));
        }

        return $value;
    }

    public function environment(): Environment
    {
        $value = $this->config->get('efatura.middleware.environment');

        if ($value instanceof Environment) {
            return $value;
        }

        if (\is_int($value)) {
            $environment = $this->parseEnvironmentFromInt($value);

            if ($environment instanceof Environment) {
                return $environment;
            }
        }

        if (\is_string($value)) {
            $environment = $this->parseEnvironmentFromString($value);

            if ($environment instanceof Environment) {
                return $environment;
            }
        }

        $this->fail('middleware.environment', __('efatura.config.environment_invalid'));
    }

    public function repositoryCode(): int
    {
        return $this->environment()->code();
    }

    /**
     * @return array<string, array<string, string|int>>
     */
    public function asArray(): array
    {
        return [
            'transmitter' => [
                'nif' => $this->transmitterNif(),
                'led' => $this->transmitterLedCode(),
            ],
            'software' => [
                'code'    => $this->softwareCode(),
                'name'    => $this->softwareName(),
                'version' => $this->softwareVersion(),
            ],
            'middleware' => [
                'base_url'        => $this->middlewareBaseUrl(),
                'environment'     => $this->environment()->name,
                'repository_code' => $this->repositoryCode(),
            ],
        ];
    }

    private function getString(string $key): string
    {
        $value = $this->config->get($key);

        if (\is_string($value)) {
            return trim($value);
        }

        if (\is_int($value)) {
            return (string) $value;
        }

        return '';
    }

    private function parseEnvironmentFromInt(int $value): ?Environment
    {
        return Environment::tryFrom($value);
    }

    private function parseEnvironmentFromString(string $value): ?Environment
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return Environment::TEST;
        }

        $environment = Environment::fromName(strtoupper($normalized));

        if ($environment instanceof Environment) {
            return $environment;
        }

        if (ctype_digit($normalized)) {
            return Environment::tryFrom((int) $normalized);
        }

        return null;
    }

    private function fail(string $field, string $message): never
    {
        throw new EfaturaValidationException($field, $message);
    }
}
