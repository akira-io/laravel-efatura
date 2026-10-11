<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

interface QrCodeRenderer
{
    public function render(string $payload): string;
}
