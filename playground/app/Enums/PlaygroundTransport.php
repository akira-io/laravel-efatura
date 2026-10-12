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

    public function label(): string
    {
        return match ($this) {
            self::Fake => 'Fake transport (no network, every document is authorized)',
            self::Configured => 'Configured driver (efatura.driver: '.config()->string('efatura.driver').')',
        };
    }
}
