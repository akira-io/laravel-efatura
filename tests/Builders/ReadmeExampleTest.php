<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Illuminate\Support\Str;

it('runs the published quick start as a canonical exact value invoice', function (): void {
    $readme   = file_get_contents(__DIR__ . '/../../README.md');
    $example  = Str::of($readme)->after('## Quick Start')->after('```php')->before('```')->toString();
    $document = null;
    eval($example);
    expect($document)->toBeInstanceOf(ElectronicInvoiceData::class)
        ->and($document->emitter->address->countryCode)->toBe('CV')
        ->and($document->header->ledCode)->toBe(1)
        ->and($document->totals->payableAmount->getAmount()->isEqualTo('115'))->toBeTrue();
});
