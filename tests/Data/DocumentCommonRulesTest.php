<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Tests\Fixtures\CommonRulesDocumentData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;

it('applies the common emitter rules to a document with empty document rules', function (): void {
    $payload = ['header' => F::payload()['header'], 'emitter' => [...F::payload()['emitter'], 'contacts' => null]];

    expect(fn (): array => CommonRulesDocumentData::validate($payload))
        ->toFailValidationOn('emitter.contacts', 'The emitter.contacts field is required.');
});

it('adds no document specific rule when a document declares none', function (): void {
    $payload = ['header' => F::payload()['header'], 'emitter' => F::payload()['emitter']];

    expect(CommonRulesDocumentData::validate($payload))->toEqual($payload);
});

it('makes every concrete document declare its own document rules', function (): void {
    expect(new ReflectionMethod(DocumentData::class, 'documentRules')->isAbstract())->toBeTrue();
});
