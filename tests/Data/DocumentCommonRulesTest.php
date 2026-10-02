<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Fixtures\CommonRulesDocumentData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;

it('applies the common emitter rules to a document without rules of its own', function (): void {
    $payload = ['header' => F::payload()['header'], 'emitter' => [...F::payload()['emitter'], 'contacts' => null]];

    expect(fn (): array => CommonRulesDocumentData::validate($payload))
        ->toFailValidationOn('emitter.contacts', 'The emitter.contacts field is required.');
});

it('adds no document specific rule to a document without rules of its own', function (): void {
    $payload = ['header' => F::payload()['header'], 'emitter' => F::payload()['emitter']];

    expect(CommonRulesDocumentData::validate($payload))->toEqual($payload);
});
