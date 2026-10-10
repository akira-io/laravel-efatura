<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\IudData;
use Akira\Efatura\Enums\IudSegment;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\Luhn;
use Random\Randomizer;

final readonly class BuildIudAction
{
    public function __construct(private Randomizer $randomizer) {}

    public function handle(IudData $data): string
    {
        $data = IudData::from($data);

        $payload = IudSegment::Repository->padded($data->repository->value)
            . Fiscal::format($data->issueDate, 'ymd', instant: true)
            . $data->emitterTaxId
            . IudSegment::LedCode->padded($data->ledCode)
            . IudSegment::DocumentType->padded($data->documentType->code())
            . IudSegment::DocumentNumber->padded($data->documentNumber)
            . ($data->randomCode ?? IudSegment::RandomCode->padded($this->randomizer->getInt(0, 10 ** IudSegment::RandomCode->length() - 1)));

        return Fiscal::COUNTRY . $payload . Luhn::checkDigit($payload);
    }
}
