<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\EventIdData;
use Akira\Efatura\Enums\EventIdSegment;
use Akira\Efatura\Support\Fiscal;

final readonly class BuildEventIdAction
{
    public function handle(EventIdData $data): string
    {
        $data = EventIdData::from($data);

        return Fiscal::COUNTRY
            . EventIdSegment::Repository->padded($data->repository->value)
            . Fiscal::format($data->issueDateTime, 'ymdHis', instant: true)
            . $data->taxId;
    }
}
