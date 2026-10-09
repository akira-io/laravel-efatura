<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Xml\Serializers\PartyXmlSerializer;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final class XmlPartFixtures
{
    public static function party(string $name, ?PartyData $party, string $path = 'emitter'): string
    {
        return XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(PartyXmlSerializer::class)->append($xml, $root, $name, $party, $path));
    }

    public static function fullAddress(): array
    {
        return [
            'countryCode'    => 'CV',
            'state'          => 'Santiago',
            'city'           => 'Praia',
            'region'         => 'Plateau',
            'street'         => 'Rua 5 de Julho',
            'streetDetail'   => 'Esquina',
            'buildingName'   => 'Edifício Ação',
            'buildingNumber' => '12',
            'buildingFloor'  => '3',
            'postalCode'     => '7600',
            'addressDetail'  => 'São Filipe & Co',
            'addressCode'    => 'CV111111111011110101',
        ];
    }

    public static function chargeLine(): array
    {
        return DocumentFixtures::linePayload([
            'lineTypeCode'       => 'C',
            'id'                 => 'L2',
            'lineReferenceId'    => 'L1',
            'orderLineReference' => 7,
            'quantity'           => ['value' => '2.5', 'unitCode' => 'C62', 'isStandardUnitCode' => true],
            'price'              => '40',
            'priceExtension'     => '100',
            'discount'           => ['value' => '12.125', 'valueType' => 'P'],
            'netTotal'           => '87.875',
            'taxes'              => [
                ['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => '13.18125'],
                ['taxTypeCode' => 'IR', 'taxPercentage' => '10'],
            ],
            'item' => [
                'description'            => 'Product <A&B>',
                'emitterIdentification'  => 'SKU',
                'packQuantity'           => ['value' => '6', 'unitCode' => 'C62'],
                'name'                   => 'Pack',
                'brandName'              => 'Brand',
                'modelName'              => 'Model',
                'standardIdentification' => ['gtin' => '0123'],
                'hazardousRiskIndicator' => false,
                'extraProperties'        => [['name' => 'Colour', 'value' => 'Red & "blue" <dark>'], ['name' => 'Weight', 'value' => '12']],
            ],
        ]);
    }

    public static function fullTotals(): array
    {
        return DocumentFixtures::totalsPayload([
            'chargeTotalAmount'         => '10',
            'discountTotalAmount'       => '5.50000',
            'discount'                  => ['value' => '5.5', 'valueType' => 'P'],
            'withholdingTaxTotalAmount' => '10',
            'payableRoundingAmount'     => '-0.01',
            'payableAmount'             => '104.99',
            'payableAlternativeAmounts' => [
                ['value' => '0.95218', 'currencyCode' => 'EUR', 'exchangeRate' => '110.265'],
                ['value' => '1.04468', 'currencyCode' => 'USD', 'exchangeRate' => '100.50000'],
            ],
        ]);
    }
}
