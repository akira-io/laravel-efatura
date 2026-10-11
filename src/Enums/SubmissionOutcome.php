<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum SubmissionOutcome: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Partial  = 'partial';
}
