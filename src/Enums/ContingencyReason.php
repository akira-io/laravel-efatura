<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum ContingencyReason: string
{
    case Other                           = '0';
    case AuthorizationServiceUnavailable = '1';
    case PowerFailure                    = '2';
    case TaxpayerSystemUnavailable       = '3';
    case InternetUnavailable             = '4';
    case TimestampServiceUnavailable     = '5';

    /**
     * @return list<self>
     */
    public static function allowedFor(EmissionMode $mode): array
    {
        return match ($mode) {
            EmissionMode::Online  => [],
            EmissionMode::Offline => [self::Other, self::AuthorizationServiceUnavailable, self::InternetUnavailable, self::TimestampServiceUnavailable],
            EmissionMode::Off     => [self::Other, self::PowerFailure, self::TaxpayerSystemUnavailable],
        };
    }
}
