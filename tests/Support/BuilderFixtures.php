<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Builders\InvoiceBuilder;
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

    public static function issuance(array $header): InvoiceBuilder
    {
        return Efatura::invoice()->emitter(self::emitter(), 1)->header(DocumentHeaderData::from($header))
            ->receiver(PartyData::from(DocumentFixtures::payload()['receiver']))
            ->line(DocumentFixtures::line())->totals(DocumentFixtures::totals());
    }
}
