<?php

declare(strict_types=1);

namespace App\Enums;

enum PlaygroundTransport: string
{
    case Fake = 'fake';
    case Configured = 'configured';

    public static function configured(): self
    {
        return self::from(config()->string('playground.transport'));
    }
}
