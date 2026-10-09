<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildDocumentXmlAction;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Tests\Support\DocumentXmlGraphs as X;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(X::NOW);
});

it('writes every document type byte for byte as its reviewed fixture', function (DocumentType $type): void {
    $document = X::document($type);

    expect(resolve(BuildDocumentXmlAction::class)->handle($document, X::iud($document), Environment::Test))->toBe(X::fixture($type));
})->with(DocumentType::cases());

it('writes the envelope attributes and the repository from the identifier', function (): void {
    $document = X::document(DocumentType::CreditNote);
    $iud      = X::iud($document, ['repositoryCode' => Environment::Production->value]);

    expect(resolve(BuildDocumentXmlAction::class)->handle($document, $iud, Environment::Production))
        ->toStartWith('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<Dfe xmlns="urn:cv:efatura:xsd:v1.0" Version="1.0" Id="' . $iud . '" DocumentTypeCode="5"><CreditNote>')
        ->toEndWith('</Transmission><RepositoryCode>1</RepositoryCode></Dfe>' . "\n");
});

it('marks a specimen only when asked, as the first child of the envelope', function (bool $isSpecimen, string $firstChild): void {
    $document = X::document(DocumentType::Invoice);

    $xml = resolve(BuildDocumentXmlAction::class)->handle($document, X::iud($document), Environment::Test, $isSpecimen);

    expect(Str::after($xml, 'DocumentTypeCode="1">'))->toStartWith($firstChild)
        ->and(substr_count($xml, 'IsSpecimen'))->toBe($isSpecimen ? 2 : 0);
})->with([
    'specimen' => [true, '<IsSpecimen>true</IsSpecimen><Invoice>'],
    'real'     => [false, '<Invoice>'],
]);

it('rejects an identifier that names another document', function (array $overrides): void {
    $document = X::document(DocumentType::Invoice);
    $iud      = X::iud($document, $overrides);

    expect(fn (): string => resolve(BuildDocumentXmlAction::class)->handle($document, $iud, Environment::Test))
        ->toFailValidationOn('iud', 'The iud does not identify this document.');
})->with([
    'issue date'      => [['issueDate' => '2026-10-01']],
    'emitter tax id'  => [['emitterTaxId' => '123456789']],
    'led code'        => [['ledCode' => 1]],
    'document type'   => [['documentTypeCode' => 'FRE']],
    'document number' => [['documentNumber' => 1]],
    'repository'      => [['repositoryCode' => Environment::Homologation->value]],
]);

it('rejects an identifier with a wrong check digit', function (): void {
    $document = X::document(DocumentType::Invoice);
    $iud      = X::iud($document);
    $wrong    = substr($iud, 0, -1) . ((int) substr($iud, -1) + 1) % 10;

    expect(fn (): string => resolve(BuildDocumentXmlAction::class)->handle($document, $wrong, Environment::Test))
        ->toFailValidationOn('iud', 'The iud must be an official IUD with a valid check digit.');
});

it('requires the document number before comparing it with the identifier', function (): void {
    $document = X::document(DocumentType::Invoice);
    $header   = DocumentHeaderData::from([...$document->header->toPayload(), 'documentNumber' => null]);
    $draft    = ElectronicInvoiceData::from([...$document->toPayload(), 'header' => $header]);

    expect(fn (): string => resolve(BuildDocumentXmlAction::class)->handle($draft, X::iud($document), Environment::Test))
        ->toFailValidationOn('header.documentNumber', 'The header.documentNumber is required to write the XML document.');
});

it('requires the transmission when the xml is written', function (): void {
    $document = X::document(DocumentType::Invoice);
    $staged   = ElectronicInvoiceData::from([...$document->toPayload(), 'emission' => null]);

    expect(fn (): string => resolve(BuildDocumentXmlAction::class)->handle($staged, X::iud($document), Environment::Test))
        ->toFailValidationOn('emission', 'The emission is required to write the XML document.');
});

it('validates a document constructed directly before writing any xml', function (): void {
    $document = X::document(DocumentType::Invoice);
    $line     = $document->lines[0];
    $invalid  = new ElectronicInvoiceData(
        header: $document->header,
        emitter: $document->emitter,
        receiver: $document->receiver,
        lines: [new LineItemData(
            $line->quantity,
            new ItemData(str_repeat('a', 301), 'SKU'),
            price: $line->price,
            priceExtension: $line->priceExtension,
            netTotal: $line->netTotal,
            taxes: $line->taxes,
        )],
        totals: $document->totals,
        emission: $document->emission,
    );

    expect(fn (): string => resolve(BuildDocumentXmlAction::class)->handle($invalid, X::iud($document), Environment::Test))
        ->toFailValidationOn('lines.0.item.description', 'The lines.0.item.description field must not be greater than 300 characters.');
});

it('accepts a document constructed directly once it validates', function (): void {
    $document = X::document(DocumentType::DebitNote);
    $direct   = new DebitNoteData(
        header: $document->header,
        emitter: $document->emitter,
        receiver: $document->receiver,
        lines: $document->lines,
        totals: $document->totals,
        issueReason: $document->issueReason,
        references: $document->references,
        emission: $document->emission,
        footer: $document->footer,
    );

    expect(resolve(BuildDocumentXmlAction::class)->handle($direct, X::iud($document), Environment::Test))->toBe(X::fixture(DocumentType::DebitNote));
});

it('writes the emitter tax id in the party and the transmitter tax id in the transmission', function (): void {
    $document = X::document(DocumentType::Invoice);

    expect(resolve(BuildDocumentXmlAction::class)->handle($document, X::iud($document), Environment::Test))
        ->toContain('<EmitterParty><TaxId CountryCode="CV">100200300</TaxId>')
        ->toContain('<TransmitterTaxId CountryCode="CV">123456789</TransmitterTaxId>')
        ->and($document->emitter->taxId?->value)->not->toBe($document->emission?->transmitterTaxId?->value);
});

it('compares the identifier date with the cabo verde issue date of an instant', function (): void {
    $document = X::document(DocumentType::Invoice);
    $instant  = new CarbonImmutable('2026-10-03T00:30:00Z');
    $header   = DocumentHeaderData::from([...$document->header->toPayload(), 'issueDate' => $instant, 'issueTime' => $instant]);
    $late     = ElectronicInvoiceData::from([...$document->toPayload(), 'header' => $header]);

    expect(resolve(BuildDocumentXmlAction::class)->handle($late, X::iud($document, ['issueDate' => '2026-10-02']), Environment::Test))
        ->toContain('<IssueDate>2026-10-02</IssueDate><IssueTime>23:30:00</IssueTime>');
});
