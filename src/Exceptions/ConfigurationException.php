<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

final class ConfigurationException extends EfaturaException
{
    public function __construct(string $errorCode, string $field)
    {
        parent::__construct($errorCode, $errorCode . ': ' . $field, $field);
    }
}
