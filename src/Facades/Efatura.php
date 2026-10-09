<?php

declare(strict_types=1);

namespace Akira\Efatura\Facades;

use Akira\Efatura\Builders\DocumentBuilder;
use Akira\Efatura\Builders\EventBuilder;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Efatura as EfaturaEntry;
use Akira\Efatura\EfaturaManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static EfaturaConfig   config()
 * @method static EfaturaEntry    efatura()
 * @method static EventBuilder    event()
 * @method static DocumentBuilder invoice()
 * @method static EfaturaManager  withConfig(EfaturaConfig $config)
 */
final class Efatura extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EfaturaManager::class;
    }
}
