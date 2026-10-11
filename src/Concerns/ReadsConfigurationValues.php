<?php

declare(strict_types=1);

namespace Akira\Efatura\Concerns;

use Akira\Efatura\Exceptions\ConfigurationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use SensitiveParameter;

use const FILTER_VALIDATE_INT;

trait ReadsConfigurationValues
{
    use ValidatesConfigurationValues;

    abstract private function repository(): Repository;

    private function section(string $path, bool $nullable = false): void
    {
        $section = $this->repository()->get($path, []);
        if ($nullable && $section === null) {
            return;
        }

        if (! \is_array($section)) {
            throw new ConfigurationException('configuration.invalid_type', $path);
        }

        foreach (collect($section)->keys() as $key) {
            if (! \is_string($key)) {
                throw new ConfigurationException('configuration.invalid_type', $path);
            }
        }
    }

    private function string(string $path, #[SensitiveParameter] ?string $default = null, bool $secret = false): ?string
    {
        $value = $this->repository()->get($path, $default);
        if ($value === null) {
            return null;
        }

        if (! \is_string($value)) {
            throw new ConfigurationException('configuration.invalid_type', $path);
        }

        $normalized = Str::trim($value, " \n\r\t\v\0");
        if ($normalized === '') {
            throw new ConfigurationException('configuration.empty_string', $path);
        }

        return $secret ? $value : $normalized;
    }

    private function requiredString(string $path, #[SensitiveParameter] ?string $default = null): string
    {
        return $this->string($path, $default) ?? throw new ConfigurationException('configuration.invalid_type', $path);
    }

    private function integer(string $path, int $default, int $minimum = 1): int
    {
        $value = $this->repository()->get($path) ?? $default;
        if (\is_string($value) && ctype_digit($value)) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
        }

        if (! \is_int($value) || $value < $minimum) {
            throw new ConfigurationException('configuration.invalid_integer', $path);
        }

        return $value;
    }

    private function boolean(string $path, bool $default): bool
    {
        $value = $this->repository()->get($path) ?? $default;
        if (! \is_bool($value)) {
            throw new ConfigurationException('configuration.invalid_type', $path);
        }

        return $value;
    }

    private function url(string $path, #[SensitiveParameter] ?string $default = null, bool $allowQuery = false): ?string
    {
        return $this->validatedUrl($this->string($path, $default), $path, $allowQuery);
    }
}
