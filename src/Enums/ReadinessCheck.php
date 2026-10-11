<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum ReadinessCheck: string
{
    case Configuration = 'configuration';
    case Certificate   = 'certificate';
    case Sequences     = 'sequences';
    case Environment   = 'environment';
    case Endpoint      = 'endpoint';
    case Clock         = 'clock';
    case Credentials   = 'credentials';

    public function isRemote(): bool
    {
        return \in_array($this, [self::Endpoint, self::Clock, self::Credentials], true);
    }
}
