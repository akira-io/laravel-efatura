<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\IudData;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\Luhn;
use Illuminate\Support\Str;
use Random\Randomizer;

final readonly class BuildIudAction
{
    private const int LARGEST_RANDOM_CODE = 9_999_999_999;

    public function __construct(private Randomizer $randomizer) {}

    public function handle(IudData $data): string
    {
        $data = IudData::from($data);

        $payload = $data->repository->value
            . $data->issueDate->format('ymd')
            . $data->emitterTaxId
            . Str::padLeft((string) $data->ledCode, 5, '0')
            . Str::padLeft((string) $data->documentType->code(), 2, '0')
            . Str::padLeft((string) $data->documentNumber, 9, '0')
            . ($data->randomCode ?? Str::padLeft((string) $this->randomizer->getInt(0, self::LARGEST_RANDOM_CODE), 10, '0'));

        return Fiscal::COUNTRY . $payload . Luhn::checkDigit($payload);
    }
}
