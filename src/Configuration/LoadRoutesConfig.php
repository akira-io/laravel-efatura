<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\ReadsConfigurationValues;
use Akira\Efatura\Exceptions\ConfigurationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use SensitiveParameter;

final readonly class LoadRoutesConfig
{
    use ReadsConfigurationValues;

    private const string PATH = 'efatura.routes';

    private const string SEGMENT = '[A-Za-z\d][A-Za-z\d._~-]*';

    public function __construct(#[SensitiveParameter] private Repository $repository) {}

    public function __invoke(): RoutesConfig
    {
        $this->section(self::PATH);

        return $this->boolean(self::PATH . '.enabled', false) ? $this->inspect() : new RoutesConfig;
    }

    public function inspect(): RoutesConfig
    {
        $this->section(self::PATH);
        $defaults   = new RoutesConfig;
        $enabled    = $this->boolean(self::PATH . '.enabled', $defaults->enabled);
        $middleware = $this->middleware();

        if ($enabled && $middleware === []) {
            throw new ConfigurationException('configuration.unprotected_routes', self::PATH . '.middleware');
        }

        return new RoutesConfig(
            $enabled,
            $this->prefix($defaults->prefix),
            $this->name($defaults->name),
            $middleware,
            $this->uploadLimit($defaults->maxUploadKilobytes),
            $this->boolean(self::PATH . '.remote_readiness', $defaults->remoteReadiness),
        );
    }

    private function repository(): Repository
    {
        return $this->repository;
    }

    /**
     * @return list<string>
     */
    private function middleware(): array
    {
        $path       = self::PATH . '.middleware';
        $middleware = $this->repository->get($path) ?? [];

        if (! \is_array($middleware) || ! array_is_list($middleware)) {
            throw new ConfigurationException('configuration.invalid_type', $path);
        }

        return array_map(
            static fn (mixed $entry): string => \is_string($entry) && Str::trim($entry) !== ''
                ? Str::trim($entry)
                : throw new ConfigurationException('configuration.invalid_type', $path),
            $middleware,
        );
    }

    private function prefix(string $default): string
    {
        $path   = self::PATH . '.prefix';
        $prefix = Str::trim($this->requiredString($path, $default), '/');

        if (preg_match('#\A' . self::SEGMENT . '(?:/' . self::SEGMENT . ')*\z#', $prefix) !== 1) {
            throw new ConfigurationException('configuration.invalid_route_prefix', $path);
        }

        return $prefix;
    }

    private function name(string $default): string
    {
        $path = self::PATH . '.name';
        $name = $this->requiredString($path, $default);

        if (preg_match('/\A[A-Za-z\d_-]+(?:\.[A-Za-z\d_-]+)*\.\z/', $name) !== 1) {
            throw new ConfigurationException('configuration.invalid_route_name', $path);
        }

        return $name;
    }

    private function uploadLimit(int $default): int
    {
        $path  = self::PATH . '.max_upload_kilobytes';
        $limit = $this->integer($path, $default);

        if ($limit > RoutesConfig::MAX_UPLOAD_KILOBYTES) {
            throw new ConfigurationException('configuration.invalid_integer', $path);
        }

        return $limit;
    }
}
