<?php

declare(strict_types=1);

namespace Akira\Efatura\Events;

use Akira\Efatura\Packaging\PreparedEvent;

final readonly class EventPrepared
{
    public function __construct(public PreparedEvent $prepared) {}
}
