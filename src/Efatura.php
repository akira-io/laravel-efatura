<?php

declare(strict_types=1);

namespace Akira\Efatura;

use Akira\Efatura\Builders\EventBuilder;
use Akira\Efatura\Builders\InvoiceBuilder;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\Clock;

final readonly class Efatura
{
    public function __construct(private EfaturaConfig $config) {}

    public function config(): EfaturaConfig
    {
        return $this->config;
    }

    public function invoice(): InvoiceBuilder
    {
        return new InvoiceBuilder($this->config, resolve(Clock::class));
    }

    public function event(): EventBuilder
    {
        return new EventBuilder($this->config, resolve(Clock::class));
    }
}
