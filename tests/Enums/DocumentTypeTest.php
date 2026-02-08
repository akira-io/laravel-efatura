<?php

declare(strict_types=1);

use Akira\Efatura\Enums\DocumentType;

it('includes all official document types', function (): void {
    $values = array_map(static fn (DocumentType $type): string => $type->value, DocumentType::cases());
    sort($values);

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
        expect($type->isSupported())->toBeTrue();
    }
});
