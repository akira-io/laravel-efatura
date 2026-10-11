<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Symfony\Component\Yaml\Yaml;

final class Workflows
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        $paths = glob(self::directory() . '/*.yml') ?: [];

        return array_map(static fn (string $path): string => basename($path, '.yml'), $paths);
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(string $name): array
    {
        return Yaml::parseFile(self::directory() . '/' . $name . '.yml');
    }

    public static function source(string $name): string
    {
        return (string) file_get_contents(self::directory() . '/' . $name . '.yml');
    }

    /**
     * @return list<string>
     */
    public static function actions(string $name): array
    {
        $jobs    = self::read($name)['jobs'];
        $actions = [];

        foreach ($jobs as $job) {
            $actions = [...$actions, ...array_filter([$job['uses'] ?? null]), ...array_column($job['steps'] ?? [], 'uses')];
        }

        return array_values($actions);
    }

    public static function directory(): string
    {
        return \dirname(__DIR__, 2) . '/.github/workflows';
    }
}
