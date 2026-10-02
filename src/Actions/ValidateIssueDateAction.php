<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Psr\Clock\ClockInterface;

final readonly class ValidateIssueDateAction
{
    private const int ONLINE_PAST_HOURS = 24;

    private const int ONLINE_FUTURE_HOURS = 1;

    private const int CONTINGENCY_PAST_DAYS = 7;

    public function __construct(private ClockInterface $clock) {}

    public function handle(DocumentHeaderData $header, EmissionMode $mode): void
    {
        $issued   = Fiscal::local($header->issueDate)->setTimeFrom(Fiscal::local($header->issueTime));
        $now      = CarbonImmutable::instance($this->clock->now())->setTimezone(Fiscal::TIMEZONE);
        $online   = $mode === EmissionMode::Online;
        $earliest = $online ? $now->subHours(self::ONLINE_PAST_HOURS) : $now->subDays(self::CONTINGENCY_PAST_DAYS);

        if ($issued->lessThan($earliest) || ($online && $issued->greaterThan($now->addHours(self::ONLINE_FUTURE_HOURS)))) {
            throw ValidationException::withMessages(['header.issueDate' => __('efatura::efatura.validation.issue_date_window')]);
        }
    }
}
