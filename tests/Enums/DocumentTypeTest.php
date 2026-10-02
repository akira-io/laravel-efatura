<?php

declare(strict_types=1);

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Support\DefaultDocumentTypePolicy;

it('includes all official document types', function (): void {
    $values = collect(DocumentType::cases())
        ->map(static fn (DocumentType $type): string => $type->value)
        ->sort()
        ->values()
        ->all();

    expect($values)->toBe([
        'DTE',
        'DVE',
        'FRE',
        'FTE',
        'NCE',
        'NDE',
        'NLE',
        'RCE',
        'TVE',
    ]);
});

it('marks supported document types', function (): void {
    foreach (DocumentType::cases() as $type) {
        expect($type)->toBeInstanceOf(DocumentType::class);
    }
});

it('covers policy support', function (): void {
    $policy = new DefaultDocumentTypePolicy;

    expect($policy->supportsEmission(DocumentType::Invoice))->toBeTrue()
        ->and($policy->supportsEmission(DocumentType::RegistrationNote))->toBeFalse();
});
