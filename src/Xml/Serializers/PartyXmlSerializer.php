<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\AddressData;
use Akira\Efatura\Data\ContactsData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\PartyReference;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class PartyXmlSerializer
{
    public function append(XmlWriter $xml, DOMElement $parent, string $name, ?PartyData $party, string $path): ?DOMElement
    {
        if (! $party instanceof PartyData) {
            return null;
        }

        $element = $xml->container($parent, $name);

        if ($party->reference instanceof PartyReference) {
            $xml->requiredElement($element, 'Reference', $party->reference->value, $path . '.reference');

            return $element;
        }

        $this->taxId($xml, $element, 'TaxId', $xml->required($party->taxId, $path . '.taxId'), $path . '.taxId');
        $xml->requiredElement($element, 'Name', $party->name, $path . '.name');
        $this->address($xml, $element, $party->address, $path . '.address');
        $this->contacts($xml, $element, $party->contacts, $path . '.contacts');

        return $element;
    }

    public function taxId(XmlWriter $xml, DOMElement $parent, string $name, TaxIdData $taxId, string $path): DOMElement
    {
        $element = $xml->requiredElement($parent, $name, $taxId->value, $path . '.value');
        $xml->attribute($element, 'CountryCode', $taxId->countryCode, $path . '.countryCode');

        return $element;
    }

    public function address(XmlWriter $xml, DOMElement $parent, ?AddressData $address, string $path): ?DOMElement
    {
        if (! $address instanceof AddressData) {
            return null;
        }

        $element = $xml->container($parent, 'Address');
        $xml->attribute($element, 'CountryCode', $address->countryCode, $path . '.countryCode');

        $xml->elements($element, $path, [
            'State'          => ['state', $address->state],
            'City'           => ['city', $address->city],
            'Region'         => ['region', $address->region],
            'Street'         => ['street', $address->street],
            'StreetDetail'   => ['streetDetail', $address->streetDetail],
            'BuildingName'   => ['buildingName', $address->buildingName],
            'BuildingNumber' => ['buildingNumber', $address->buildingNumber],
            'BuildingFloor'  => ['buildingFloor', $address->buildingFloor],
            'PostalCode'     => ['postalCode', $address->postalCode],
        ]);
        $xml->requiredElement($element, 'AddressDetail', $address->addressDetail, $path . '.addressDetail');
        $xml->element($element, 'AddressCode', $address->addressCode, $path . '.addressCode');

        return $element;
    }

    public function delivery(XmlWriter $xml, DOMElement $parent, ?DeliveryData $delivery, string $path): ?DOMElement
    {
        if (! $delivery instanceof DeliveryData) {
            return null;
        }

        $element = $xml->container($parent, 'Delivery');
        $xml->requiredElement($element, 'DeliveryDate', XmlValue::date($delivery->deliveryDate), $path . '.deliveryDate');
        $this->address($xml, $element, $delivery->address, $path . '.address');

        return $element;
    }

    private function contacts(XmlWriter $xml, DOMElement $parent, ?ContactsData $contacts, string $path): void
    {
        if (! $contacts instanceof ContactsData) {
            return;
        }

        $xml->elements($xml->container($parent, 'Contacts'), $path, [
            'Telephone'   => ['telephone', $contacts->telephone],
            'Mobilephone' => ['mobilephone', $contacts->mobilephone],
            'Telefax'     => ['telefax', $contacts->telefax],
            'Email'       => ['email', $contacts->email],
            'Website'     => ['website', $contacts->website],
        ]);
    }
}
