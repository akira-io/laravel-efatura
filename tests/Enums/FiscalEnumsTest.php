<?php

declare(strict_types=1);

use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Enums\TaxType;

it('maps every official document acronym to its numeric code and XML element', function (): void {
    expect(array_map(
        static fn (DocumentType $type): array => [$type->value, $type->code(), $type->xmlElement()],
        DocumentType::cases(),
    ))->toBe([
        ['FTE', 1, 'Invoice'],
        ['FRE', 2, 'InvoiceReceipt'],
        ['TVE', 3, 'SalesReceipt'],
        ['RCE', 4, 'Receipt'],
        ['NCE', 5, 'CreditNote'],
        ['NDE', 6, 'DebitNote'],
        ['DTE', 7, 'Transport'],
        ['DVE', 8, 'ReturnNote'],
        ['NLE', 9, 'RegistrationNote'],
    ]);
});

it('defines only active schema codes for modes, events, tax, reasons, and discounts', function (): void {
    expect(array_column(EmissionMode::cases(), 'value'))->toBe([1, 2, 3])
        ->and(array_column(EventType::cases(), 'value'))->toBe(['FDC', 'UDN'])
        ->and(array_column(TaxType::cases(), 'value'))->toBe(['NA', 'IVA', 'IS', 'IR'])
        ->and(array_column(IssueReason::cases(), 'value'))->toBe(['0', '2', '3', '4', '6', '7', '8', '9', 'DD', 'IN', 'DRP'])
        ->and(array_column(DiscountValueType::cases(), 'value'))->toBe(['A', 'P'])
        ->and(TaxType::tryFrom('TEU'))->toBeNull()
        ->and(IssueReason::tryFrom('drp'))->toBeNull();
});

it('applies official line signs and excludes information lines from totals', function (): void {
    expect(array_map(static fn (LineType $line): array => [$line->value, $line->sign(), $line->participatesInTotals()], LineType::cases()))
        ->toBe([['N', 1, true], ['C', 1, true], ['D', -1, true], ['I', 0, false]])
        ->and(LineType::tryFrom('n'))->toBeNull();
});
