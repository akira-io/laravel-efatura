<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use RuntimeException;

final class ConfigurationException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly string $field)
    {
        parent::__construct($errorCode . ': ' . $field);
    }
}
