<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use Throwable;

abstract class ResourceException extends EfaturaException
{
    public function __construct(string $errorCode, string $operation, ?Throwable $previous = null)
    {
        parent::__construct($errorCode, $errorCode, context: ['operation' => $operation], previous: $previous);
    }
}
