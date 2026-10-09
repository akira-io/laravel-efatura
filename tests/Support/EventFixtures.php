<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

final class EventFixtures
{
    public static function payload(array $overrides = []): array
    {
        return array_replace([
            'emitterTaxId'           => ['value' => '100200300', 'countryCode' => 'CV'],
            'issueDateTime'          => '2026-10-02T12:00:00',
            'issueReasonDescription' => 'Document cancelled by emitter',
            'eventTypeCode'          => 'FDC',
        ], $overrides);
    }

    public static function iud(): string
    {
        return 'CV1261002100200300' . str_repeat('0', 27);
    }

    public static function numberRange(int $start, int $end): array
    {
        return ['ledCode' => 1, 'serie' => 'A', 'documentTypeCode' => 'FTE', 'documentNumberStart' => $start, 'documentNumberEnd' => $end];
    }
}
