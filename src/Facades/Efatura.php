<?php

declare(strict_types=1);

namespace Akira\Efatura\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Akira\Efatura\Efatura
 */
final class Efatura extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Akira\Efatura\Efatura::class;
    }
}
