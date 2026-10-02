<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Data\PartyData;

final class BuilderFixtures
{
    public static function emitter(): PartyData
    {
        return PartyData::from([...DocumentFixtures::payload()['emitter'],
            'address' => ['countryCode' => 'CV', 'addressDetail' => 'Praia office', 'addressCode' => 'CV111111111011110101']]);
    }
}
