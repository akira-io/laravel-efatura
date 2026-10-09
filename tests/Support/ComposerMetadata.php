<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Illuminate\Filesystem\Filesystem;

use const JSON_THROW_ON_ERROR;

final class ComposerMetadata
{
    /**
     * @return array<string, mixed>
     */
    public static function read(): array
    {
        return new Filesystem()->json(\dirname(__DIR__, 2) . '/composer.json', JSON_THROW_ON_ERROR);
    }
}
