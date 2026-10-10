<?php

declare(strict_types=1);

use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Tests\Support\IdentifierFixtures as I;

it('recognizes a Cabo Verde tax id with one shared pattern', function (string $taxId, bool $valid): void {
    expect(FiscalRules::isCvTaxId($taxId))->toBe($valid)
        ->and(validator(['taxId' => $taxId], ['taxId' => FiscalRules::cvTaxId()])->passes())->toBe($valid);
})->with([
    'nine digits'         => ['100200300', true],
    'eight digits'        => ['10020030', false],
    'ten digits'          => ['1002003000', false],
    'leading zero'        => ['010020030', false],
    'trailing line break' => ["100200300\n", false],
]);

it('leaves the clock of an event id to the date validation', function (): void {
    expect(FiscalRules::isEventId(I::OFFICIAL_EVENT_ID))->toBeTrue()
        ->and(FiscalRules::isEventId('CV1210805246099123456789'))->toBeTrue();
});
