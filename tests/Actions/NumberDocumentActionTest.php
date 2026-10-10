<?php

declare(strict_types=1);

use Akira\Efatura\Actions\NumberDocumentAction;
use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\IudSegment;
use Akira\Efatura\Sequence\InMemorySequenceStore;
use Akira\Efatura\Sequence\NumberedDocument;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\SequenceFixtures as S;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    $this->store = new InMemorySequenceStore;
    app()->instance(SequenceStore::class, $this->store);
    $this->action = resolve(NumberDocumentAction::class);
});

it('reserves consecutive numbers and binds each one to its iud', function (): void {
    $first  = $this->action->handle(S::invoice(['ledCode' => 7]), Environment::Test);
    $second = $this->action->handle(S::invoice(['ledCode' => 7]), Environment::Homologation);
    $parsed = resolve(ParseIudAction::class)->handle($second->iud);

    expect($first->allocated)->toBeTrue()
        ->and($first->document->header->documentNumber)->toBe(1)
        ->and(resolve(ParseIudAction::class)->handle($first->iud)->documentNumber)->toBe(1)
        ->and($second->document->header->documentNumber)->toBe(2)
        ->and($parsed->documentNumber)->toBe(2)
        ->and($parsed->emitterTaxId)->toBe('100200300')
        ->and($parsed->ledCode)->toBe(7)
        ->and($parsed->documentType)->toBe(DocumentType::Invoice)
        ->and($parsed->repository)->toBe(Environment::Homologation)
        ->and($parsed->issueDate->format('Y-m-d'))->toBe('2026-10-02')
        ->and($this->store->current(S::scope(ledCode: 7)))->toBe(2);
});

it('keeps the transmitter out of the scope and the iud', function (): void {
    $own      = $this->action->handle(S::invoice(), Environment::Test);
    $relay    = [...DocumentPayloads::transmission(), 'transmitterTaxId' => ['value' => '900800700', 'countryCode' => 'CV']];
    $other    = S::invoice(overrides: ['emission' => $relay]);
    $relayed  = $this->action->handle($other, Environment::Test);
    $segments = fn (NumberedDocument $numbered): string => IudSegment::EmitterTaxId->of($numbered->iud) . IudSegment::LedCode->of($numbered->iud);

    expect($other->emission?->transmitterTaxId->value)->toBe('900800700')
        ->and($relayed->document->header->documentNumber)->toBe(2)
        ->and($segments($relayed))->toBe($segments($own))
        ->and($this->store->current(S::scope()))->toBe(2);
});

it('resumes a document that already carries its number without reserving', function (): void {
    $numbered = $this->action->handle(S::invoice(['documentNumber' => 42]), Environment::Test);

    expect($numbered->allocated)->toBeFalse()
        ->and($numbered->document->header->documentNumber)->toBe(42)
        ->and(resolve(ParseIudAction::class)->handle($numbered->iud)->documentNumber)->toBe(42)
        ->and($this->store->current(S::scope()))->toBe(0);
});

it('validates a document constructed directly before reserving', function (): void {
    $document = S::invoice();
    $invalid  = new ElectronicInvoiceData(
        header: new DocumentHeaderData($document->header->issueDate, $document->header->issueTime, 0),
        emitter: $document->emitter,
        receiver: $document->receiver,
        lines: $document->lines,
        totals: $document->totals,
        emission: $document->emission,
    );

    expect(fn (): NumberedDocument => $this->action->handle($invalid, Environment::Test))
        ->toFailValidationOn('header.ledCode', 'The header.led code field must be between 1 and 99999.')
        ->and($this->store->current(S::scope()))->toBe(0);
});
