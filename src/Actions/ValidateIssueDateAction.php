<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Contracts\Clock;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Enums\EmissionMode;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class ValidateIssueDateAction
{
    public function __construct(private Clock $clock) {}

    public function handle(DocumentHeaderData $header, EmissionMode $mode): void
    {
        $issued   = new CarbonImmutable($header->issueDate->format('Y-m-d') . 'T' . $header->issueTime->format('H:i:s'), 'Atlantic/Cape_Verde');
        $now      = $this->clock->now();
        $earliest = $mode === EmissionMode::Online ? $now->subHours(24) : $now->subDays(7);
        if ($issued->lessThan($earliest) || ($mode === EmissionMode::Online && $issued->greaterThan($now->addHour()))) {
            throw ValidationException::withMessages(['header.issueDate' => __('efatura::efatura.validation.issue_date_window')]);
        }
    }
}
