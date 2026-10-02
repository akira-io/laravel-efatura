<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Contracts\Clock;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class ValidateIssueDateAction
{
    public function __construct(private Clock $clock) {}

    public function handle(DocumentHeaderData $header, EmissionMode $mode): void
    {
        $issued   = new CarbonImmutable($header->issueDate->format(Fiscal::DATE_FORMAT) . 'T' . $header->issueTime->format(Fiscal::TIME_FORMAT), Fiscal::TIMEZONE);
        $now      = $this->clock->now();
        $earliest = $mode === EmissionMode::Online ? $now->subHours(24) : $now->subDays(7);
        if ($issued->lessThan($earliest) || ($mode === EmissionMode::Online && $issued->greaterThan($now->addHour()))) {
            throw ValidationException::withMessages(['header.issueDate' => __('efatura::efatura.validation.issue_date_window')]);
        }
    }
}
