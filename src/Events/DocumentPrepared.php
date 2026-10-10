<?php

declare(strict_types=1);

namespace Akira\Efatura\Events;

use Akira\Efatura\Packaging\PreparedDocument;

final readonly class DocumentPrepared
{
    public function __construct(public PreparedDocument $prepared) {}
}
