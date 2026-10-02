<?php

declare(strict_types=1);

use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\QuantityData;
use Brick\Math\BigDecimal;

dataset('factories', ['from' => ['from'], 'validateAndCreate' => ['validateAndCreate']]);
dataset('zero quantities', fn (): array => [
    'data object' => [new QuantityData(BigDecimal::of('0'), 'C62')],
    'array'       => [['value' => '0', 'unitCode' => 'C62']],
]);

it('rejects a zero line quantity', function (QuantityData|array $quantity, string $method): void {
    $payload = ['quantity' => $quantity, 'item' => ['description' => 'Item', 'emitterIdentification' => 'SKU']];

    expect(fn (): LineItemData => LineItemData::$method($payload))
        ->toFailValidationOn('quantity.value', 'The quantity.value is outside its permitted numeric bounds.');
})->with('zero quantities')->with('factories');

it('rejects a zero item pack quantity', function (QuantityData|array $quantity, string $method): void {
    $payload = ['description' => 'Item', 'emitterIdentification' => 'SKU', 'packQuantity' => $quantity];

    expect(fn (): ItemData => ItemData::$method($payload))
        ->toFailValidationOn('packQuantity.value', 'The pack quantity.value is outside its permitted numeric bounds.');
})->with('zero quantities')->with('factories');

it('accepts the smallest positive line and pack quantity', function (): void {
    $positive = new QuantityData(BigDecimal::of('0.00001'), 'C62');
    $line     = new LineItemData($positive, new ItemData('Item', 'SKU', packQuantity: $positive));

    expect((string) $line->quantity->value)->toBe('0.00001')
        ->and((string) $line->item->packQuantity?->value)->toBe('0.00001');
});
