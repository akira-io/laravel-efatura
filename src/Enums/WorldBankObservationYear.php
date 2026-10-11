<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum WorldBankObservationYear: string
{
    case Previous = 'previous';
    case Request  = 'request';

    public function year(int $requestYear): int
    {
        return $this === self::Previous ? $requestYear - 1 : $requestYear;
    }
}
