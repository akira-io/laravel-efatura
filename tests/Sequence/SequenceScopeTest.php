<?php

declare(strict_types=1);

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Sequence\InMemorySequenceStore;
use Akira\Efatura\Sequence\SequenceScope;
use Akira\Efatura\Tests\Support\SequenceFixtures as S;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('scopes a document by its emitter, cape verde year, led and type', function (): void {
    $scope = SequenceScope::forDocument(S::invoice(['ledCode' => 7]));

    expect($scope)->toEqual(S::scope(ledCode: 7))
        ->and($scope->key())->toBe('100200300:2026:7:1');
});

it('uses the emitter tax id and never the transmitter one', function (): void {
    $document = S::invoice();

    expect($document->emission?->transmitterTaxId->value)->toBe('123456789')
        ->and(SequenceScope::forDocument($document)->emitterTaxId)->toBe('100200300');
});

it('dates the scope on the cape verde year of the issue instant', function (): void {
    $instant = CarbonImmutable::parse('2027-01-01T00:30:00Z');
    CarbonImmutable::setTestNow($instant);

    expect(SequenceScope::forDocument(S::invoice(['issueDate' => $instant, 'issueTime' => $instant]))->year)->toBe(2026);
});

it('restarts the count on the first of january', function (): void {
    $store = new InMemorySequenceStore;
    CarbonImmutable::setTestNow('2026-12-31T23:00:00-01:00');
    $december = SequenceScope::forDocument(S::invoice(['issueDate' => '2026-12-31', 'issueTime' => '22:00:00']));
    CarbonImmutable::setTestNow('2027-01-01T01:00:00-01:00');
    $january = SequenceScope::forDocument(S::invoice(['issueDate' => '2027-01-01', 'issueTime' => '00:30:00']));

    expect($store->next($december))->toBe(1)
        ->and($store->next($december))->toBe(2)
        ->and($store->next($january))->toBe(1);
});

it('shares one count between the series of a scope', function (): void {
    $first  = SequenceScope::forDocument(S::invoice(['serie' => 'A']));
    $second = SequenceScope::forDocument(S::invoice(['serie' => 'B']));

    expect($second)->toEqual($first)
        ->and($second->key())->toBe($first->key());
});

it('refuses a scope outside the fiscal identifiers', function (string $taxId, int $year, int $ledCode): void {
    expect(fn (): SequenceScope => new SequenceScope($taxId, $year, $ledCode, DocumentType::Invoice))
        ->toThrow(DefinitionException::class, 'A sequence scope needs a Cabo Verde tax id, a fiscal year from 2021 to 2099 and a LED code from 1 to 99999.');
})->with([
    'short tax id'        => ['10020030', 2026, 1],
    'year before 2021'    => ['100200300', 2020, 1],
    'year 2100'           => ['100200300', 2100, 1],
    'five digit year'     => ['100200300', 10000, 1],
    'led zero'            => ['100200300', 2026, 0],
    'led with six digits' => ['100200300', 2026, 100000],
]);

it('accepts the bounds of the fiscal identifiers', function (): void {
    expect(S::scope(year: 2021, ledCode: 99999)->key())->toBe('100200300:2021:99999:1')
        ->and(S::scope(year: 2099, documentType: DocumentType::RegistrationNote)->key())->toBe('100200300:2099:1:9');
});
