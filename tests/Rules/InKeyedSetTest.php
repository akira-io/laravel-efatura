<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Rules\InKeyedSet;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;

it('accepts members of the keyed set by their string form', function (mixed $value): void {
    expect(validator(['reference' => $value], ['reference' => [new InKeyedSet(['A', '0', 7])]])->passes())->toBeTrue();
})->with(['text' => ['A'], 'zero text' => ['0'], 'integer' => [7], 'zero integer' => [0]]);

it('rejects values outside the keyed set with the selected-value message', function (mixed $value): void {
    $validator = validator(['reference' => $value], ['reference' => [new InKeyedSet(['A'])]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('reference'))->toBe('The selected reference is invalid.');
})->with(['unknown' => ['B'], 'list' => [['A']], 'case' => ['a']]);

it('checks every line reference against one set of line ids', function (): void {
    $lines   = array_map(static fn (int $index): array => F::linePayload(['id' => 'L' . $index, 'lineReferenceId' => 'L' . (($index + 1) % 300)]), range(0, 299));
    $lines[] = F::linePayload(['lineReferenceId' => 'missing']);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['lines' => $lines])))
        ->toFailValidationOn('lines.300.lineReferenceId', 'The selected lines.300.lineReferenceId is invalid.');
});
