<?php

declare(strict_types=1);

use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\SoftwareData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\XmlFragment;
use Akira\Efatura\Xml\Serializers\TransmissionXmlSerializer;
use Akira\Efatura\Xml\XmlWriter;

it('writes an online transmission without contingency', function (): void {
    $emission = EmissionContextData::from(DocumentPayloads::transmission());

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TransmissionXmlSerializer::class)->append($xml, $root, $emission, 'emission')))->toBe(
        '<Transmission><IssueMode>1</IssueMode><TransmitterTaxId CountryCode="CV">123456789</TransmitterTaxId>'
        . '<Software><Code>APP</Code><Name>Fiscal App</Name><Version>1.0</Version></Software></Transmission>',
    );
});

it('writes an offline transmission with the contingency time', function (): void {
    $emission = EmissionContextData::from([...DocumentPayloads::transmission(), 'issueMode' => 2, 'contingency' => DocumentPayloads::offlineContingency()]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TransmissionXmlSerializer::class)->append($xml, $root, $emission, 'emission')))
        ->toContain('<IssueMode>2</IssueMode>')
        ->toEndWith('</Software><Contingency><LedCode>1</LedCode><IssueDate>2026-10-02</IssueDate><IssueTime>12:00:00</IssueTime>'
            . '<ReasonTypeCode>4</ReasonTypeCode></Contingency></Transmission>');
});

it('writes an off transmission with its IUC and reason description', function (): void {
    $contingency = DocumentPayloads::contingency(ContingencyReason::PowerFailure);
    unset($contingency['issueTime']);
    $emission = EmissionContextData::from([...DocumentPayloads::transmission(), 'issueMode' => 3, 'contingency' => [...$contingency, 'ledCode' => 7]]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TransmissionXmlSerializer::class)->append($xml, $root, $emission, 'emission')))
        ->toContain('<IssueMode>3</IssueMode>')
        ->toEndWith('<Contingency><LedCode>7</LedCode><IUC>2026/1</IUC><IssueDate>2026-10-02</IssueDate><ReasonTypeCode>2</ReasonTypeCode>'
            . '<ReasonDescription>Temporary service interruption</ReasonDescription></Contingency></Transmission>');
});

it('requires the transmission parts the staged payload may leave out', function (?EmissionContextData $emission, string $field): void {
    expect(fn (): string => XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TransmissionXmlSerializer::class)->append($xml, $root, $emission, 'emission')))
        ->toFailValidationOn($field, 'The ' . $field . ' is required to write the XML document.');
})->with([
    'emission'    => [null, 'emission'],
    'transmitter' => [fn (): EmissionContextData => new EmissionContextData(software: new SoftwareData('APP', 'Fiscal App', '1.0')), 'emission.transmitterTaxId'],
    'software'    => [fn (): EmissionContextData => new EmissionContextData(transmitterTaxId: new TaxIdData('123456789', 'CV')), 'emission.software'],
]);

it('reports invalid software text on its wire path', function (): void {
    $emission = new EmissionContextData(transmitterTaxId: new TaxIdData('123456789', 'CV'), software: new SoftwareData('APP', "Fiscal\x00App", '1.0'));

    expect(fn (): string => XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TransmissionXmlSerializer::class)->append($xml, $root, $emission, 'emission')))
        ->toFailValidationOn('emission.software.name', 'The emission.software.name contains characters that XML 1.0 does not allow.');
});
