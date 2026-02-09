<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

final class Trans
{
    /**
     * @param array<string, string> $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $message = __($key, $replace);

        return \is_string($message) ? $message : '';
    }
}
