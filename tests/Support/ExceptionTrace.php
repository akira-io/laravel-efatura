<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Throwable;

final class ExceptionTrace
{
    /**
     * @return list<array<array-key, mixed>>
     */
    public static function packageArguments(Throwable $exception): array
    {
        return collect($exception->getTrace())
            ->takeUntil(fn (array $frame): bool => str_starts_with($frame['class'] ?? '', 'P\\'))
            ->pluck('args')
            ->values()
            ->all();
    }

    public static function exposed(Throwable $exception): string
    {
        $exposed = '';
        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            $properties = collect((array) $current)
                ->reject(fn (mixed $value, string $key): bool => str_ends_with($key, "\0trace") || str_ends_with($key, "\0previous"))
                ->all();
            $exposed .= print_r($properties, true) . var_export($properties, true) . json_encode($current) . print_r(self::sourceArguments($current), true);
        }

        return $exposed;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private static function sourceArguments(Throwable $exception): array
    {
        return collect($exception->getTrace())
            ->filter(fn (array $frame): bool => str_starts_with($frame['class'] ?? '', 'Akira\Efatura\\') && ! str_starts_with($frame['class'] ?? '', 'Akira\Efatura\Tests\\'))
            ->pluck('args')
            ->values()
            ->all();
    }
}
