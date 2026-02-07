<?php

namespace Akira\Efatura\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Akira\Efatura\Efatura
 */
class Efatura extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Akira\Efatura\Efatura::class;
    }
}
