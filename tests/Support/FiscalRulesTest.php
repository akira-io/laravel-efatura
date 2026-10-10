<?php

declare(strict_types=1);

use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Tests\Support\IdentifierFixtures as I;

it('leaves the clock of an event id to the date validation', function (): void {
    expect(FiscalRules::isEventId(I::OFFICIAL_EVENT_ID))->toBeTrue()
        ->and(FiscalRules::isEventId('CV1210805246099123456789'))->toBeTrue();
});
