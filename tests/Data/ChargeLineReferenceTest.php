<?php

declare(strict_types=1);

use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Enums\LineType;
use Brick\Math\BigDecimal;

dataset('factories', ['from' => ['from'], 'validateAndCreate' => ['validateAndCreate']]);
dataset('charge line', fn (): array => [[[
    'quantity'     => ['value' => '1', 'unitCode' => 'C62'],
    'item'         => ['description' => 'Item', 'emitterIdentification' => 'SKU'],
    'lineTypeCode' => LineType::Charge,
]]]);

it('requires a line reference on a charge line', function (array $payload, string $method): void {
    expect(fn (): LineItemData => LineItemData::$method($payload))
        ->toFailValidationOn('lineReferenceId', 'The line reference id field is required.');
})->with('charge line')->with('factories');

it('accepts a charge line reference through the factories without checking cross line existence', function (array $payload, string $method): void {
    $input = [...$payload, 'lineReferenceId' => 'L1'];

    expect(LineItemData::$method($input)->lineReferenceId)->toBe('L1');
})->with('charge line')->with('factories');

it('accepts a charge line reference through the constructor', function (): void {
    $line = new LineItemData(new QuantityData(BigDecimal::of('1'), 'C62'), new ItemData('Item', 'SKU'), LineType::Charge, lineReferenceId: 'L1');

    expect($line->lineReferenceId)->toBe('L1');
});
