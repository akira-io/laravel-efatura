<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Data\TransportRouteData;
use Illuminate\Validation\ValidationException;

it('creates nested data lists from arrays', function (): void {
    $footer = DocumentFooterData::from(['extraFields' => [['name' => 'CustomerTag', 'value' => 'blue']]]);

    expect($footer->extraFields)->toHaveCount(1)
        ->and($footer->extraFields[0])->toBeInstanceOf(ExtraFieldData::class)
        ->and($footer->extraFields[0]->value)->toBe('blue');
});

it('reports an invalid nested list item at its full path', function (): void {
    $location = ['address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon'], 'duration' => ['startDate' => '2026-10-02', 'startTime' => '13:00:00'], 'transportModeCode' => '3'];
    $invalid  = array_replace_recursive($location, ['address' => ['countryCode' => 'ZZ']]);

    try {
        TransportRouteData::validateAndCreate(['locations' => [$location, $invalid]]);
        test()->fail('Expected a ValidationException.');
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey('locations.1.address.countryCode');
    }
});
