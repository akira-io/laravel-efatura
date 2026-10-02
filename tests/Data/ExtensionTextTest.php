<?php

declare(strict_types=1);

use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Data\ExtraPropertyData;

dataset('blank texts', ['empty' => [''], 'spaces' => ['   '], 'tab and newline' => ["\t\n"]]);
dataset('factories', ['from' => ['from'], 'validateAndCreate' => ['validateAndCreate']]);

it('preserves explicitly permitted empty extension text through the constructor', function (string $value): void {
    expect(new ExtraFieldData('CustomFlag', $value)->value)->toBe($value);
})->with('blank texts');

it('preserves explicitly permitted empty extension text through the factories', function (string $value, string $method): void {
    $payload = ['name' => 'CustomFlag', 'value' => $value];

    expect(ExtraFieldData::$method($payload)->toArray()['value'])->toBe($value);
})->with('blank texts')->with('factories');

it('still requires valid extension names when their text is empty', function (string $name, string $method): void {
    $payload = ['name' => $name, 'value' => ''];

    expect(fn (): ExtraFieldData => ExtraFieldData::$method($payload))->toFailValidationOn('name', 'The name field is required.');
})->with('blank texts')->with('factories');

it('requires nonempty clean text for item properties', function (string $value, string $method): void {
    $payload = ['name' => 'CustomFlag', 'value' => $value];

    expect(fn (): ExtraPropertyData => ExtraPropertyData::$method($payload))->toFailValidationOn('value', 'The value field is required.');
})->with('blank texts')->with('factories');
