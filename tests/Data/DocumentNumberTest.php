<?php

declare(strict_types=1);

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Tests\Support\DocumentXmlGraphs;
use Akira\Efatura\Tests\Support\SequenceFixtures as S;
use Akira\Efatura\Tests\Support\XmlFixtures;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(DocumentXmlGraphs::NOW);
});

it('changes only the document number of the fullest graph', function (DocumentType $type): void {
    $document = XmlFixtures::maximal($type);
    $expected = $document->toPayload();
    data_set($expected, 'header.documentNumber', 7);

    $renumbered = $document->withDocumentNumber(7);

    expect($renumbered)->toBeInstanceOf($type->dataClass())
        ->and($renumbered->header->documentNumber)->toBe(7)
        ->and($renumbered->toPayload())->toBe($expected)
        ->and($document->header->documentNumber)->toBe(999_999_999);
})->with(DocumentType::cases());

it('numbers a document that had no number yet', function (): void {
    $document = S::invoice();

    expect($document->header->documentNumber)->toBeNull()
        ->and($document->withDocumentNumber(1)->header->documentNumber)->toBe(1);
});

it('refuses a number outside the fiscal range on the header path', function (int $number): void {
    expect(fn (): mixed => S::invoice()->withDocumentNumber($number))
        ->toFailValidationOn('header.documentNumber', 'The header.document number field must be between 1 and 999999999.');
})->with([
    'zero'           => [0],
    'beyond maximum' => [1_000_000_000],
]);
