<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

final class PackagingException extends EfaturaException
{
    /**
     * @param array<string, int|string> $context
     */
    public function __construct(string $errorCode, array $context = [])
    {
        parent::__construct($errorCode, $errorCode, context: $context);
    }
}
