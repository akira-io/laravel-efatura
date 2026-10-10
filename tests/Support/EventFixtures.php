<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Actions\BuildEventIdAction;
use Akira\Efatura\Data\EventIdData;
use Akira\Efatura\Enums\Environment;

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
        return 'CV1261002100200300' . str_repeat('0', 26) . '5';
    }

    public static function numberRange(int $start, int $end): array
    {
        return ['ledCode' => 1, 'serie' => 'A', 'documentTypeCode' => 'FTE', 'documentNumberStart' => $start, 'documentNumberEnd' => $end];
    }

    public static function eventId(array $overrides = []): string
    {
        return resolve(BuildEventIdAction::class)->handle(EventIdData::from([
            'repositoryCode' => Environment::Test->value,
            'issueDateTime'  => '2026-10-02T12:00:00',
            'taxId'          => '123456789',
            ...$overrides,
        ]));
    }

    public static function transmitted(array $overrides = []): array
    {
        return self::payload(['emission' => DocumentPayloads::transmission(), ...$overrides]);
    }
}
