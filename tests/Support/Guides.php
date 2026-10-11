<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Illuminate\Support\Str;

final class Guides
{
    public const string INDEX = '00-index.md';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(basename(...), glob(self::directory() . '/*.md') ?: []);
    }

    public static function directory(): string
    {
        return \dirname(__DIR__, 2) . '/docs';
    }

    public static function read(string $name): string
    {
        return (string) file_get_contents(self::directory() . '/' . $name);
    }

    public static function footer(string $name): string
    {
        $names    = self::names();
        $position = (int) array_search($name, $names, true);
        $parts    = [];

        if ($position > 1) {
            $parts[] = 'Previous: \[[^\]\n]+\]\(' . preg_quote($names[$position - 1], '/') . '\)';
        }

        if ($position > 0) {
            $parts[] = '\[Index\]\(' . preg_quote(self::INDEX, '/') . '\)';
        }

        if (isset($names[$position + 1])) {
            $parts[] = 'Next: \[[^\]\n]+\]\(' . preg_quote($names[$position + 1], '/') . '\)';
        }

        return '/\n---\n\n' . implode(' · ', $parts) . '\n$/u';
    }

    /**
     * @return list<string>
     */
    public static function brokenLinks(string $file): array
    {
        preg_match_all('/\]\(([^)\s]+)\)/', (string) file_get_contents($file), $links);
        $broken = [];

        foreach ($links[1] as $link) {
            if (! preg_match('#^(https?:|mailto:)#', $link) && ! self::resolves($file, $link)) {
                $broken[] = basename($file) . ' -> ' . $link;
            }
        }

        return $broken;
    }

    private static function resolves(string $file, string $link): bool
    {
        [$path, $anchor] = array_pad(explode('#', $link, 2), 2, null);
        $target          = $path === '' ? $file : \dirname($file) . '/' . $path;

        if (! file_exists($target)) {
            return false;
        }

        return $anchor === null || \in_array($anchor, self::anchors($target), true);
    }

    /**
     * @return list<string>
     */
    private static function anchors(string $file): array
    {
        preg_match_all('/^#{1,6} (.+)$/m', is_file($file) ? (string) file_get_contents($file) : '', $headings);

        return array_map(
            static fn (string $heading): string => Str::of($heading)->lower()->replaceMatches('/[^\p{L}\p{N}\s_-]/u', '')->replace(' ', '-')->toString(),
            $headings[1],
        );
    }
}
