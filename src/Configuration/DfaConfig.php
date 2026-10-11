<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Support\Fiscal;

final readonly class DfaConfig
{
    public function __construct(
        public string $baseUrl = Fiscal::DFA_BASE_URL,
        public ?string $cachePath = null,
    ) {}
}
