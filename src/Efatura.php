<?php

declare(strict_types=1);

namespace Akira\Efatura;

use Akira\Efatura\Configuration\EfaturaConfig;

final readonly class Efatura
{
    public function __construct(private EfaturaConfig $config) {}

    public function config(): EfaturaConfig
    {
        return $this->config;
    }
}
