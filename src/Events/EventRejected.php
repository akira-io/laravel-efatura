<?php

declare(strict_types=1);

namespace Akira\Efatura\Events;

use Akira\Efatura\Packaging\PreparedEvent;
use Akira\Efatura\Transport\EntryResult;

final readonly class EventRejected
{
    public function __construct(public PreparedEvent $prepared, public EntryResult $result) {}
}
