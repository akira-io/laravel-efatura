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
}
