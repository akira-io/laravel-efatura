<?php

declare(strict_types=1);

use Akira\Efatura\Data\ContactsData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EventNumberRangeData;
use Akira\Efatura\Data\FiscalDocumentData;
use Akira\Efatura\Data\QuantityData;
use Illuminate\Validation\ValidationException;

dataset('ascii punctuation between Z and a', ['[', '\\', ']', '^', '`']);

it('rejects punctuation inside a document series', function (string $character): void {
    expect(fn (): DocumentHeaderData => DocumentHeaderData::from(['issueDate' => '2026-10-02', 'issueTime' => '09:00:00', 'ledCode' => 1, 'serie' => 'A' . $character . 'B']))
        ->toThrow(ValidationException::class);
})->with('ascii punctuation between Z and a');

it('rejects a leading underscore in a document series', function (): void {
    expect(fn (): DocumentHeaderData => DocumentHeaderData::from(['issueDate' => '2026-10-02', 'issueTime' => '09:00:00', 'ledCode' => 1, 'serie' => '_AB']))
        ->toThrow(ValidationException::class);
});

it('rejects punctuation inside an event number range series', function (string $character): void {
    expect(fn (): EventNumberRangeData => EventNumberRangeData::from([
        'ledCode' => 1, 'serie' => 'A' . $character . 'B', 'documentTypeCode' => 'FTE', 'documentNumberStart' => 1, 'documentNumberEnd' => 2,
    ]))->toThrow(ValidationException::class);
})->with('ascii punctuation between Z and a');

it('rejects punctuation inside a fiscal document reference series', function (string $series): void {
    expect(fn (): FiscalDocumentData => FiscalDocumentData::from(['value' => '1/2026/' . $series . '/1']))
        ->toThrow(ValidationException::class);
})->with(['A[B', 'A\B', 'A]B', 'A^B', 'A`B', '_AB']);

it('rejects punctuation inside a unit code', function (string $character): void {
    expect(fn (): QuantityData => QuantityData::from(['value' => '1', 'unitCode' => 'C' . $character . '2']))
        ->toThrow(ValidationException::class);
})->with([...['[', '\\', ']', '^', '`'], '_']);

it('rejects punctuation inside a website host', function (string $character): void {
    expect(fn (): ContactsData => ContactsData::from(['website' => 'www.exa' . $character . 'mple.cv']))
        ->toThrow(ValidationException::class);
})->with('ascii punctuation between Z and a');

it('keeps accepting alphanumeric series, references, units and websites', function (): void {
    expect(DocumentHeaderData::from(['issueDate' => '2026-10-02', 'issueTime' => '09:00:00', 'ledCode' => 1, 'serie' => 'Ab9_C-1'])->series)->toBe('Ab9_C-1')
        ->and(FiscalDocumentData::from(['value' => '1/2026/Ab9_C-1/123', 'isOldDocument' => true])->value)->toBe('1/2026/Ab9_C-1/123')
        ->and(QuantityData::from(['value' => '1', 'unitCode' => 'aZ09'])->unitCode)->toBe('aZ09')
        ->and(ContactsData::from(['website' => 'https://www.Exa_mple-1.cv:8080/a.b?x=1&y=%2B#top'])->website)->toBe('https://www.Exa_mple-1.cv:8080/a.b?x=1&y=%2B#top');
});
