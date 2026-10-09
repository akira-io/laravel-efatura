<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Support\ValidatedData;
use Akira\Efatura\Tests\Support\BuilderFixtures as B;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Brick\Math\BigDecimal;

it('accepts a thousand validated lines and rejects one more', function (): void {
    $line = F::line();

    expect(ElectronicInvoiceData::from(P::invoiceWithLines(array_fill(0, 1000, $line)))->lines)->toHaveCount(1000)
        ->and(fn (): DocumentData => ElectronicInvoiceData::from(P::invoiceWithLines(array_fill(0, 1001, $line))))
        ->toFailValidationOn('lines', 'The lines field must not have more than 1000 items.');
});

it('rejects more than a thousand references', function (): void {
    $reference = ReferenceData::from(F::references()[0]);

    expect(fn (): DocumentData => ElectronicInvoiceData::from(F::payload(['references' => array_fill(0, 1001, $reference)])))
        ->toFailValidationOn('references', 'The references field must not have more than 1000 items.');
});

it('still applies the document rules to validated line objects', function (): void {
    $line = F::line(['id' => 'A']);

    expect(fn (): DocumentData => ElectronicInvoiceData::from(P::invoiceWithLines([$line, $line])))
        ->toFailValidationOn('lines.1.id', 'The lines.1.id field has a duplicate value.')
        ->and(fn (): DocumentData => ElectronicInvoiceData::from(F::payload(['lines' => [F::line(['price' => null])]])))
        ->toFailValidationOn('lines.0.price', 'The lines.0.price field is required.');
});

it('validates a constructed line object inside the builder', function (): void {
    $line    = new LineItemData(new QuantityData(BigDecimal::of('0'), 'C62'), new ItemData('Item', 'SKU'));
    $builder = Efatura::invoice()->emitter(B::emitter(), 1)->receiver(B::receiver())->line($line)->totals(F::totals());

    expect(fn (): DocumentData => $builder->build())
        ->toFailValidationOn('lines.0.quantity.value', 'The lines.0.quantity.value is outside its permitted numeric bounds.');
});

it('does not trust a validation marker supplied in an array payload', function (): void {
    $line = [...F::linePayload(['quantity' => ['value' => '0', 'unitCode' => 'C62']]), ValidatedData::KEY => 'forged'];

    expect(fn (): DocumentData => ElectronicInvoiceData::from(F::payload(['lines' => [$line]])))
        ->toFailValidationOn('lines.0.quantity.value', 'The lines.0.quantity.value is outside its permitted numeric bounds.');
});
