<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Builders\DocumentBuilder;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Facades\Efatura;

final class BuilderFixtures
{
    public static function emitter(): PartyData
    {
        return PartyData::from([...DocumentFixtures::payload()['emitter'],
            'address' => ['countryCode' => 'CV', 'addressDetail' => 'Praia office', 'addressCode' => 'CV111111111011110101']]);
    }

    public static function receiver(): PartyData
    {
        return PartyData::from(DocumentFixtures::payload()['receiver']);
    }

    public static function issuance(array $header): DocumentBuilder
    {
        return Efatura::invoice()->emitter(self::emitter(), 1)->header(DocumentHeaderData::from($header))
            ->receiver(self::receiver())->line(DocumentFixtures::line())->totals(DocumentFixtures::totals());
    }

    public static function completeEmitterConfig(): array
    {
        return [
            'tax_id'  => '100200300', 'name' => 'Default emitter', 'led' => '11',
            'address' => [
                'country_code'  => 'CV', 'address_detail' => 'Praia office', 'address_code' => 'CV111111111011110101',
                'state'         => 'Santiago', 'region' => 'Praia', 'city' => 'Praia', 'street' => 'Main street', 'street_detail' => 'East',
                'building_name' => 'Office', 'building_number' => '1', 'building_floor' => '2', 'postal_code' => '7600',
            ],
            'contacts' => [
                'email'   => 'default@example.cv', 'telephone' => '1234567', 'mobile' => '7654321', 'telefax' => '1234568',
                'website' => 'https://example.cv',
            ],
        ];
    }

    public static function alternateEmitter(): PartyData
    {
        return PartyData::from([
            'taxId'    => ['value' => '900800700', 'countryCode' => 'CV'], 'name' => 'Other emitter',
            'address'  => ['countryCode' => 'CV', 'addressDetail' => 'Other office', 'addressCode' => 'CV111111111011110101'],
            'contacts' => ['email' => 'other@example.cv', 'mobilephone' => '9876543'],
        ]);
    }
}
