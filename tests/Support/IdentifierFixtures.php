<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Support\Luhn;

final class IdentifierFixtures
{
    public const string NODE_IUD = 'CV3260208100200300001230100000000112345678909';

    public const string OFFICIAL_EVENT_ID = 'CV1210805181011123456789';

    public static function iudPayload(array $overrides = []): array
    {
        return array_replace([
            'repositoryCode'   => 3,
            'issueDate'        => '2026-02-08',
            'emitterTaxId'     => '100200300',
            'ledCode'          => 123,
            'documentTypeCode' => 'FTE',
            'documentNumber'   => 1,
            'randomCode'       => '1234567890',
        ], $overrides);
    }

    public static function withCheckDigit(string $iudWithoutCheckDigit): string
    {
        return $iudWithoutCheckDigit . Luhn::checkDigit(substr($iudWithoutCheckDigit, 2));
    }
}
