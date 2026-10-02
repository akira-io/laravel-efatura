<?php

declare(strict_types=1);

use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Facades\Efatura;

$emitter = PartyData::from([
    'taxId'   => ['value' => '100200300', 'countryCode' => 'CV'],
    'name'    => 'Example emitter',
    'address' => [
        'countryCode' => 'CV', 'addressDetail' => 'Praia office',
        'addressCode' => 'CV111111111011110101',
    ],
    'contacts' => ['email' => 'billing@example.cv', 'telephone' => '2600000'],
]);

$document = Efatura::invoice()
    ->type(DocumentType::Invoice)
    ->emitter($emitter, ledCode: 1)
    ->receiver(PartyData::from([
        'taxId' => ['value' => '900800700', 'countryCode' => 'CV'],
        'name'  => 'Example receiver',
    ]))
    ->line(LineItemData::from([
        'quantity' => ['value' => '1', 'unitCode' => 'C62'],
        'item'     => ['description' => 'Service', 'emitterIdentification' => 'SERVICE-1'],
        'price'    => '100', 'priceExtension' => '100', 'netTotal' => '100',
        'taxes'    => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15']],
    ]))
    ->totals(TotalsData::from([
        'priceExtensionTotalAmount' => '100', 'netTotalAmount' => '100',
        'taxTotalAmount'            => '15', 'payableAmount' => '115',
    ]))
    ->build();
