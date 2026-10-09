<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\DurationData;
use Akira\Efatura\Data\TransportLocationData;
use Akira\Efatura\Data\TransportRouteData;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class TransportRouteXmlSerializer
{
    public function __construct(private PartyXmlSerializer $parties) {}

    public function append(XmlWriter $xml, DOMElement $parent, TransportRouteData $route, string $path): DOMElement
    {
        $element = $xml->container($parent, 'TransportRoute');

        foreach ($route->locations as $index => $location) {
            $this->location($xml, $element, $location, $path . '.locations.' . $index);
        }

        return $element;
    }

    private function location(XmlWriter $xml, DOMElement $parent, TransportLocationData $location, string $path): void
    {
        $element = $xml->container($parent, 'TransportLocation');

        $this->parties->address($xml, $element, $location->address, $path . '.address');
        $this->duration($xml, $element, $location->duration, $path . '.duration');
        $xml->requiredElement($element, 'TransportModeCode', $location->transportMode->value, $path . '.transportModeCode');
        $xml->element($element, 'VehicleRegistrationCode', $location->vehicleRegistrationCode, $path . '.vehicleRegistrationCode');
    }

    private function duration(XmlWriter $xml, DOMElement $parent, DurationData $duration, string $path): void
    {
        $element = $xml->container($parent, 'Duration');

        $xml->requiredElement($element, 'StartDate', XmlValue::date($duration->startDate), $path . '.startDate');
        $xml->requiredElement($element, 'StartTime', XmlValue::time($duration->startTime), $path . '.startTime');
        $xml->elements($element, $path, [
            'EndDate' => ['endDate', XmlValue::date($duration->endDate)],
            'EndTime' => ['endTime', XmlValue::time($duration->endTime)],
        ]);
    }
}
