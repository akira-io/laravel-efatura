<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\SelfBillingData;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class HeaderXmlSerializer
{
    public function header(XmlWriter $xml, DOMElement $body, DocumentHeaderData $header, string $path): void
    {
        $xml->element($body, 'IsIsolatedAct', XmlValue::boolean($header->isIsolatedAct), $path . '.isIsolatedAct');
        $this->selfBilling($xml, $body, $header->selfBilling, $path . '.selfBilling');
        $xml->requiredElement($body, 'LedCode', XmlValue::integer($header->ledCode), $path . '.ledCode');
        $xml->requiredElement($body, 'Serie', $header->series, $path . '.serie');
        $xml->requiredElement($body, 'DocumentNumber', XmlValue::integer($header->documentNumber), $path . '.documentNumber');
        $xml->element($body, 'InnerDocumentNumber', $header->innerDocumentNumber, $path . '.innerDocumentNumber');
        $xml->requiredElement($body, 'IssueDate', XmlValue::date($header->issueDate, instant: true), $path . '.issueDate');
        $xml->requiredElement($body, 'IssueTime', XmlValue::time($header->issueTime, instant: true), $path . '.issueTime');
    }

    public function footer(XmlWriter $xml, DOMElement $body, ?DocumentFooterData $footer, string $path): void
    {
        if (! $footer instanceof DocumentFooterData) {
            return;
        }

        $xml->element($body, 'Note', $footer->note, $path . '.note');

        if ($footer->extraFields === []) {
            return;
        }

        $extraFields = $xml->container($body, 'ExtraFields');

        foreach ($footer->extraFields as $index => $field) {
            $xml->foreign($extraFields, $field->name, $field->namespace, $field->value, $path . '.extraFields.' . $index . '.value');
        }
    }

    private function selfBilling(XmlWriter $xml, DOMElement $body, ?SelfBillingData $selfBilling, string $path): void
    {
        if (! $selfBilling instanceof SelfBillingData) {
            return;
        }

        $element = $xml->container($body, 'SelfBilling');
        $xml->requiredElement($element, 'AuthorizationId', $selfBilling->authorizationId, $path . '.authorizationId');
        $xml->requiredElement($element, 'AuthorizationCode', $selfBilling->authorizationCode, $path . '.authorizationCode');
    }
}
