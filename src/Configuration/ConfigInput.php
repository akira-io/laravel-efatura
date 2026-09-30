<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Exceptions\ConfigurationException;
use SensitiveParameter;

use const FILTER_VALIDATE_INT;
use const FILTER_VALIDATE_URL;

final readonly class ConfigInput
{
    /**
     * @param array<string, mixed> $settings
     */
    private function __construct(#[SensitiveParameter] private array $settings, private string $path) {}

    public static function from(#[SensitiveParameter] mixed $settings, string $path): self
    {
        if (! \is_array($settings)) {
            throw new ConfigurationException('configuration.invalid_type', $path);
        }

        $normalized = [];
        foreach ($settings as $key => $setting) {
            if (! \is_string($key)) {
                throw new ConfigurationException('configuration.invalid_type', $path);
            }

            $normalized[$key] = $setting;
        }

        return new self($normalized, $path);
    }

    public static function normalizeString(#[SensitiveParameter] mixed $setting, string $path, bool $secret = false): ?string
    {
        if ($setting === null) {
            return null;
        }

        if (! \is_string($setting)) {
            throw new ConfigurationException('configuration.invalid_type', $path);
        }

        if (trim($setting) === '') {
            throw new ConfigurationException('configuration.empty_string', $path);
        }

        return $secret ? $setting : trim($setting);
    }

    public function read(string $key, #[SensitiveParameter] mixed $default = null): mixed
    {
        return \array_key_exists($key, $this->settings) ? $this->settings[$key] : $default;
    }

    public function section(string $key, bool $nullable = false): self
    {
        $section = $this->read($key, []);

        return self::from($nullable && $section === null ? [] : $section, $this->field($key));
    }

    public function string(string $key, #[SensitiveParameter] ?string $default = null, bool $secret = false): ?string
    {
        return self::normalizeString($this->read($key, $default), $this->field($key), $secret);
    }

    public function requiredString(string $key, #[SensitiveParameter] ?string $default = null): string
    {
        return $this->string($key, $default) ?? throw new ConfigurationException('configuration.invalid_type', $this->field($key));
    }

    public function integer(string $key, int $default, int $minimum = 1): int
    {
        $setting = $this->read($key) ?? $default;
        if (\is_string($setting) && ctype_digit($setting)) {
            $setting = filter_var($setting, FILTER_VALIDATE_INT);
        }

        if (! \is_int($setting) || $setting < $minimum) {
            throw new ConfigurationException('configuration.invalid_integer', $this->field($key));
        }

        return $setting;
    }

    public function boolean(string $key, bool $default): bool
    {
        $setting = $this->read($key) ?? $default;
        if (! \is_bool($setting)) {
            throw new ConfigurationException('configuration.invalid_type', $this->field($key));
        }

        return $setting;
    }

    public function taxId(string $key): ?string
    {
        $taxId = $this->string($key);
        if ($taxId !== null && preg_match('/^[0-9]{9}$/D', $taxId) !== 1) {
            throw new ConfigurationException('configuration.invalid_tax_id', $this->field($key));
        }

        return $taxId;
    }

    public function url(string $key, #[SensitiveParameter] ?string $default = null): ?string
    {
        $url = $this->string($key, $default);
        if ($url === null) {
            return null;
        }

        $components = parse_url($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || $components === false
            || ($components['scheme'] ?? '') !== 'https' || isset($components['user'])
            || isset($components['pass']) || isset($components['query']) || isset($components['fragment'])) {
            throw new ConfigurationException('configuration.unsafe_url', $this->field($key));
        }

        return rtrim($url, '/');
    }

    public function relativePath(string $key, #[SensitiveParameter] ?string $default = null): ?string
    {
        $path = $this->string($key, $default);
        if ($path !== null && (preg_match('/[\x00-\x1f\\\:]/', $path) === 1 || str_starts_with($path, '/')
            || array_intersect(explode('/', $path), ['', '.', '..']) !== [])) {
            throw new ConfigurationException('configuration.unsafe_path', $this->field($key));
        }

        return $path;
    }

    public function identifier(string $key, #[SensitiveParameter] string $default): string
    {
        $identifier = $this->requiredString($key, $default);
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $identifier) !== 1) {
            throw new ConfigurationException('configuration.invalid_identifier', $this->field($key));
        }

        return $identifier;
    }

    private function field(string $key): string
    {
        return $this->path . '.' . $key;
    }
}
