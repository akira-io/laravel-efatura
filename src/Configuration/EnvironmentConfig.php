<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Enums\Environment;

final readonly class EnvironmentConfig
{
    public function __construct(public Environment $environment) {}

    public function repositoryCode(): int
    {
        return $this->environment->code();
    }
}
