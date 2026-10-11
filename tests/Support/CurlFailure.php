<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use RuntimeException;

final class CurlFailure extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(private readonly array $context)
    {
        parent::__construct('cURL error');
    }

    /**
     * @return array<string, mixed>
     */
    public function getHandlerContext(): array
    {
        return $this->context;
    }
}
