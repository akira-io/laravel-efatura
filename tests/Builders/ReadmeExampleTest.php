<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->example = __DIR__ . '/../../docs/examples/quick-start.php';
});

it('runs the quick start example as a canonical exact value invoice', function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');

    $document = (static function (string $example): ElectronicInvoiceData {
        require $example;

        return $document;
    })($this->example);

    expect($document->emitter->name)->toBe('Example emitter')
        ->and($document->emitter->address->countryCode)->toBe('CV')
        ->and($document->receiver->taxId->value)->toBe('900800700')
        ->and($document->header->ledCode)->toBe(1)
        ->and($document->header->issueDate->format('Y-m-d'))->toBe('2026-10-02')
        ->and($document->lines[0]->item->emitterIdentification)->toBe('SERVICE-1')
        ->and((string) $document->totals->payableAmount->getAmount())->toBe('115.00000');
});

it('publishes the quick start example verbatim in the README', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../README.md');

    expect($readme)->toContain("## Quick Start\n\n```php\n" . file_get_contents($this->example) . "```\n");
});
