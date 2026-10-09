<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Data\TransportRouteData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;

it('creates nested data lists from arrays', function (): void {
    $footer = DocumentFooterData::from(['extraFields' => [['name' => 'CustomerTag', 'value' => 'blue']]]);

    expect($footer->extraFields)->toHaveCount(1)
        ->and($footer->extraFields[0])->toBeInstanceOf(ExtraFieldData::class)
        ->and($footer->extraFields[0]->value)->toBe('blue');
});

it('reports an invalid nested list item at its full path', function (): void {
    $payload                                           = F::route();
    $payload['locations'][1]['address']['countryCode'] = 'ZZ';

    expect(fn (): TransportRouteData => TransportRouteData::validateAndCreate($payload))
        ->toFailValidationOn('locations.1.address.countryCode', 'The locations.1.address.country code must be a code in the official catalog.');
});
