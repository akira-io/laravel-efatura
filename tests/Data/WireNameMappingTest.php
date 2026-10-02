<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EventNumberRangeData;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
});

it('hydrates the header series from serie and writes it back as serie', function (): void {
    $header = DocumentHeaderData::from(['issueDate' => '2026-10-02', 'issueTime' => '09:00:00', 'ledCode' => 1, 'serie' => 'A']);

    expect($header->series)->toBe('A')
        ->and($header->toArray())->toHaveKey('serie', 'A')
        ->and($header->toArray())->not->toHaveKey('series');
});

it('hydrates the number range series from serie and writes it back as serie', function (): void {
    $range = EventNumberRangeData::from(['ledCode' => 1, 'serie' => 'A', 'documentTypeCode' => 'FTE', 'documentNumberStart' => 1, 'documentNumberEnd' => 2]);

    expect($range->series)->toBe('A')
        ->and($range->toArray())->toHaveKey('serie', 'A')
        ->and($range->toArray())->not->toHaveKey('series');
});

it('reports an invalid series under serie', function (): void {
    expect(fn (): DocumentHeaderData => DocumentHeaderData::from(['issueDate' => '2026-10-02', 'issueTime' => '09:00:00', 'ledCode' => 1, 'serie' => 'bad space']))
        ->toThrow(fn (ValidationException $exception): mixed => expect($exception->errors())->toHaveKey('serie')->not->toHaveKey('series'));
});
