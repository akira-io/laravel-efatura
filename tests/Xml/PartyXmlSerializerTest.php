<?php

declare(strict_types=1);

use Akira\Efatura\Data\AddressData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Tests\Support\DocumentFixtures;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\XmlFragment;
use Akira\Efatura\Tests\Support\XmlPartFixtures;
use Akira\Efatura\Xml\Serializers\PartyXmlSerializer;
use Akira\Efatura\Xml\XmlWriter;

it('writes an identified party in schema order', function (): void {
    expect(XmlPartFixtures::party('EmitterParty', PartyData::from(DocumentFixtures::payload()['emitter'])))->toBe(
        '<EmitterParty><TaxId CountryCode="CV">100200300</TaxId><Name>Emitter</Name>'
        . '<Address CountryCode="CV"><AddressDetail>Praia office</AddressDetail><AddressCode>CV111111111011110101</AddressCode></Address>'
        . '<Contacts><Telephone>1234567</Telephone><Email>emitter@example.cv</Email></Contacts></EmitterParty>',
    );
});

it('writes every address field with the address detail after the optional ones', function (): void {
    $party = PartyData::from([...DocumentFixtures::payload()['emitter'], 'address' => XmlPartFixtures::fullAddress(), 'contacts' => null]);

    expect(XmlPartFixtures::party('ReceiverParty', $party, 'receiver'))->toBe(
        '<ReceiverParty><TaxId CountryCode="CV">100200300</TaxId><Name>Emitter</Name><Address CountryCode="CV">'
        . '<State>Santiago</State><City>Praia</City><Region>Plateau</Region><Street>Rua 5 de Julho</Street><StreetDetail>Esquina</StreetDetail>'
        . '<BuildingName>Edifício Ação</BuildingName><BuildingNumber>12</BuildingNumber><BuildingFloor>3</BuildingFloor><PostalCode>7600</PostalCode>'
        . '<AddressDetail>São Filipe &amp; Co</AddressDetail><AddressCode>CV111111111011110101</AddressCode></Address></ReceiverParty>',
    );
});

it('writes every contact in schema order', function (): void {
    $party = PartyData::from([...DocumentFixtures::payload()['emitter'], 'contacts' => [
        'website'     => 'https://www.empresa.cv/a?b=c#d',
        'email'       => 'email@empresa.cv',
        'telefax'     => '3123456',
        'mobilephone' => '9123456',
        'telephone'   => '1234567',
    ]]);

    expect(XmlPartFixtures::party('EmitterParty', $party))->toContain(
        '<Contacts><Telephone>1234567</Telephone><Mobilephone>9123456</Mobilephone><Telefax>3123456</Telefax>'
        . '<Email>email@empresa.cv</Email><Website>https://www.empresa.cv/a?b=c#d</Website></Contacts>',
    );
});

it('writes a party given by reference alone', function (string $reference): void {
    expect(XmlPartFixtures::party('PaymentParty', PartyData::from(['reference' => $reference]), 'paymentParty'))
        ->toBe('<PaymentParty><Reference>' . $reference . '</Reference></PaymentParty>');
})->with(['EP', 'RP']);

it('writes a foreign receiver without an address', function (): void {
    expect(XmlPartFixtures::party('ReceiverParty', PartyData::from(DocumentPayloads::foreignBuyer()), 'receiver'))
        ->toBe('<ReceiverParty><TaxId CountryCode="PT">123456789</TaxId><Name>Foreign buyer</Name></ReceiverParty>');
});

it('writes nothing for an absent party', function (): void {
    expect(XmlPartFixtures::party('ReceiverParty', null, 'receiver'))->toBe('');
});

it('requires the identity of a party that has no reference', function (string $field, PartyData $party): void {
    expect(fn (): string => XmlPartFixtures::party('EmitterParty', $party))
        ->toFailValidationOn($field, 'The ' . $field . ' is required to write the XML document.');
})->with([
    'tax id' => ['emitter.taxId', fn (): PartyData => new PartyData(name: 'Emitter')],
    'name'   => ['emitter.name', fn (): PartyData => new PartyData(taxId: new TaxIdData('100200300', 'CV'))],
]);

it('reports invalid party text on its field path', function (): void {
    $party = new PartyData(new TaxIdData('100200300', 'CV'), 'Emitter', new AddressData('CV', "Praia\x01"));

    expect(fn (): string => XmlPartFixtures::party('EmitterParty', $party))
        ->toFailValidationOn('emitter.address.addressDetail', 'The emitter.address.addressDetail contains characters that XML 1.0 does not allow.');
});

it('writes a delivery with its date and address', function (): void {
    $delivery = DeliveryData::from(['deliveryDate' => '2026-10-03', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon']]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(PartyXmlSerializer::class)->delivery($xml, $root, $delivery, 'delivery')))
        ->toBe('<Delivery><DeliveryDate>2026-10-03</DeliveryDate><Address CountryCode="PT"><AddressDetail>Lisbon</AddressDetail></Address></Delivery>');
});

it('writes nothing for an absent delivery', function (): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(PartyXmlSerializer::class)->delivery($xml, $root, null, 'delivery')))
        ->toBe('');
});

it('writes a named tax identifier with its country attribute', function (): void {
    $taxId = TaxIdData::from(['value' => '123456789', 'countryCode' => 'CV']);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(PartyXmlSerializer::class)
        ->taxId($xml, $root, 'TransmitterTaxId', $taxId, 'emission.transmitterTaxId')))
        ->toBe('<TransmitterTaxId CountryCode="CV">123456789</TransmitterTaxId>');
});
