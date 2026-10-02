<?php

declare(strict_types=1);

namespace Akira\Efatura;

use Akira\Efatura\Builders\EventBuilder;
use Akira\Efatura\Builders\InvoiceBuilder;
use Akira\Efatura\Configuration\EfaturaConfig;
use Psr\Clock\ClockInterface;

final readonly class Efatura
{
    public function __construct(private EfaturaConfig $config, private ClockInterface $clock) {}

    public function config(): EfaturaConfig
    {
        return $this->config;
    }

    public function invoice(): InvoiceBuilder
    {
        return new InvoiceBuilder($this->config, $this->clock);
    }

    public function event(): EventBuilder
    {
        return new EventBuilder($this->config, $this->clock);
    }
}
