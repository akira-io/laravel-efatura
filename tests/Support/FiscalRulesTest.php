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

it('recognizes a led code with the shared led pattern', function (int $ledCode, bool $valid): void {
    expect(FiscalRules::isLedCode($ledCode))->toBe($valid)
        ->and(validator(['ledCode' => $ledCode], ['ledCode' => FiscalRules::ledCode()])->passes())->toBe($valid);
})->with([
    'lowest'     => [1, true],
    'highest'    => [99999, true],
    'zero'       => [0, false],
    'negative'   => [-1, false],
    'six digits' => [100000, false],
]);

it('bounds document numbers by the highest fiscal number', function (int $number, bool $valid): void {
    expect(validator(['number' => $number], ['number' => FiscalRules::documentNumber()])->passes())->toBe($valid);
})->with([
    'highest' => [999_999_999, true],
    'beyond'  => [1_000_000_000, false],
]);

it('leaves the clock of an event id to the date validation', function (): void {
    expect(FiscalRules::isEventId(I::OFFICIAL_EVENT_ID))->toBeTrue()
        ->and(FiscalRules::isEventId('CV1210805246099123456789'))->toBeTrue();
});
