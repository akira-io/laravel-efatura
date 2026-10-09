<?php

declare(strict_types=1);

use Akira\Efatura\Data\ContactsData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EventNumberRangeData;
use Akira\Efatura\Data\FiscalDocumentData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;

dataset('ascii punctuation between Z and a', ['[', '\\', ']', '^', '`']);

it('rejects punctuation inside a document series', function (string $character): void {
    $payload = P::header(['serie' => 'A' . $character . 'B']);

    expect(fn (): DocumentHeaderData => DocumentHeaderData::from($payload))
        ->toFailValidationOn('serie', 'The serie field format is invalid.');
})->with('ascii punctuation between Z and a');

it('rejects a leading underscore in a document series', function (): void {
    $payload = P::header(['serie' => '_AB']);

    expect(fn (): DocumentHeaderData => DocumentHeaderData::from($payload))
        ->toFailValidationOn('serie', 'The serie field format is invalid.');
});

it('rejects punctuation inside an event number range series', function (string $character): void {
    $payload = ['ledCode' => 1, 'serie' => 'A' . $character . 'B', 'documentTypeCode' => 'FTE', 'documentNumberStart' => 1, 'documentNumberEnd' => 2];

    expect(fn (): EventNumberRangeData => EventNumberRangeData::from($payload))
        ->toFailValidationOn('serie', 'The serie field format is invalid.');
})->with('ascii punctuation between Z and a');

it('rejects punctuation inside a fiscal document reference series', function (string $series): void {
    $payload = ['value' => '1/2026/' . $series . '/1'];

    expect(fn (): FiscalDocumentData => FiscalDocumentData::from($payload))
        ->toFailValidationOn('value', 'The value field format is invalid.');
})->with(['A[B', 'A\B', 'A]B', 'A^B', 'A`B', '_AB']);

it('accepts the literal XSD A-z range inside a unit code', function (string $unitCode): void {
    expect(QuantityData::from(['value' => '1', 'unitCode' => $unitCode])->unitCode)->toBe($unitCode);
})->with(['KG_X', 'C[2', 'C\2', 'C]2', 'C^2', 'C`2', 'ABCDEFGHIJ']);

it('rejects a unit code outside the literal XSD A-z range or above ten characters', function (string $unitCode): void {
    $payload = ['value' => '1', 'unitCode' => $unitCode];

    expect(fn (): QuantityData => QuantityData::from($payload))
        ->toFailValidationOn('unitCode', 'The unit code field format is invalid.');
})->with(['KG X', 'KG-X', 'KG{X', 'KG@X', 'ABCDEFGHIJK']);

it('rejects punctuation inside a website host', function (string $character): void {
    $payload = ['website' => 'www.exa' . $character . 'mple.cv'];

    expect(fn (): ContactsData => ContactsData::from($payload))
        ->toFailValidationOn('website', 'The website field format is invalid.');
})->with('ascii punctuation between Z and a');

it('keeps accepting alphanumeric values', function (Closure $create, string $expected): void {
    expect($create())->toBe($expected);
})->with([
    'series'    => [fn (): string => DocumentHeaderData::from(P::header(['serie' => 'Ab9_C-1']))->series, 'Ab9_C-1'],
    'reference' => [fn (): string => FiscalDocumentData::from(['value' => '1/2026/Ab9_C-1/123', 'isOldDocument' => true])->value, '1/2026/Ab9_C-1/123'],
    'unit code' => [fn (): string => QuantityData::from(['value' => '1', 'unitCode' => 'aZ09'])->unitCode, 'aZ09'],
    'website'   => [fn (): ?string => ContactsData::from(['website' => 'https://www.Exa_mple-1.cv:8080/a.b?x=1&y=%2B#top'])->website, 'https://www.Exa_mple-1.cv:8080/a.b?x=1&y=%2B#top'],
]);
