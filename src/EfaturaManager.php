<?php

declare(strict_types=1);

namespace Akira\Efatura;

use Akira\Efatura\Builders\EventBuilder;
use Akira\Efatura\Builders\InvoiceBuilder;
use Akira\Efatura\Configuration\EfaturaConfig;

final readonly class EfaturaManager
{
    private Efatura $efatura;

    public function __construct(private EfaturaConfig $config)
    {
        $this->efatura = new Efatura($config);
    }

    public function config(): EfaturaConfig
    {
        return $this->config;
    }

    public function withConfig(EfaturaConfig $config): self
    {
        return new self($config);
    }

    public function efatura(): Efatura
    {
        return $this->efatura;
    }

    public function invoice(): InvoiceBuilder
    {
        return $this->efatura->invoice();
    }

    public function event(): EventBuilder
    {
        return $this->efatura->event();
    }
}
