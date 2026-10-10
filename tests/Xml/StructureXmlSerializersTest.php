<?php

declare(strict_types=1);

use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\RentReceiptData;
use Akira\Efatura\Data\TransportRouteData;
use Akira\Efatura\Tests\Support\DocumentFixtures;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\EventFixtures;
use Akira\Efatura\Tests\Support\XmlFragment;
use Akira\Efatura\Xml\Serializers\PaymentXmlSerializer;
use Akira\Efatura\Xml\Serializers\ReferenceXmlSerializer;
use Akira\Efatura\Xml\Serializers\RentReceiptXmlSerializer;
use Akira\Efatura\Xml\Serializers\TransportRouteXmlSerializer;
use Akira\Efatura\Xml\XmlWriter;

it('writes references to an IUD and to an old document', function (): void {
    $references = ReferenceData::collect([
        ['fiscalDocument' => ['value' => EventFixtures::iud(), 'isOldDocument' => false]],
        ...DocumentFixtures::references(),
        ['fiscalDocument' => ['value' => EventFixtures::iud()]],
    ]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(ReferenceXmlSerializer::class)->append($xml, $root, $references, 'references')))->toBe(
        '<References><Reference><FiscalDocument IsOldDocument="false">' . EventFixtures::iud() . '</FiscalDocument></Reference>'
        . '<Reference><FiscalDocument IsOldDocument="true">1/2026/A/1</FiscalDocument></Reference>'
        . '<Reference><FiscalDocument>' . EventFixtures::iud() . '</FiscalDocument></Reference></References>',
    );
});

it('writes every reference field in schema order', function (): void {
    $references = ReferenceData::collect([[
        'fiscalDocument'      => ['value' => '1/2026/A/1', 'isOldDocument' => true],
        'innerDocumentNumber' => 'INV-7',
        'paymentAmount'       => '115.00000',
        'taxes'               => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15'], ['taxTypeCode' => 'IR', 'taxPercentage' => '10']],
    ]]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(ReferenceXmlSerializer::class)->append($xml, $root, $references, 'references')))->toBe(
        '<References><Reference><FiscalDocument IsOldDocument="true">1/2026/A/1</FiscalDocument><InnerDocumentNumber>INV-7</InnerDocumentNumber>'
        . '<PaymentAmount>115</PaymentAmount><Tax TaxTypeCode="IVA"><TaxPercentage>15</TaxPercentage></Tax>'
        . '<Tax TaxTypeCode="IR"><TaxPercentage>10</TaxPercentage></Tax></Reference></References>',
    );
});

it('writes a reference that carries a payment without a fiscal document', function (): void {
    $references = ReferenceData::collect([['paymentAmount' => '100']]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(ReferenceXmlSerializer::class)->append($xml, $root, $references, 'references')))
        ->toBe('<References><Reference><PaymentAmount>100</PaymentAmount></Reference></References>');
});

it('writes nothing when there are no references', function (): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(ReferenceXmlSerializer::class)->append($xml, $root, [], 'references')))->toBe('');
});

it('writes the invoice payments variant in schema order', function (): void {
    $payments = PaymentsData::from([
        'paymentDueDate'         => '2026-11-02',
        'paymentTerms'           => ['note' => 'Pay within thirty days'],
        'payeeFinancialAccounts' => [['name' => 'Bank Account', 'accountNumber' => '123'], ['name' => 'Other Bank', 'nib' => '123456789012345678901']],
    ]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(PaymentXmlSerializer::class)->appendInvoice($xml, $root, $payments, 'payments')))->toBe(
        '<Payments><PaymentDueDate>2026-11-02</PaymentDueDate><PaymentTerms><Note>Pay within thirty days</Note></PaymentTerms>'
        . '<PayeeFinancialAccount><AccountNumber>123</AccountNumber><Name>Bank Account</Name></PayeeFinancialAccount>'
        . '<PayeeFinancialAccount><NIB>123456789012345678901</NIB><Name>Other Bank</Name></PayeeFinancialAccount></Payments>',
    );
});

it('writes an invoice payments variant with a due date alone', function (): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(PaymentXmlSerializer::class)
        ->appendInvoice($xml, $root, PaymentsData::from(['paymentDueDate' => '2026-11-02']), 'payments')))
        ->toBe('<Payments><PaymentDueDate>2026-11-02</PaymentDueDate></Payments>');
});

it('writes nothing for invoice payments without content', function (?PaymentsData $payments): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(PaymentXmlSerializer::class)->appendInvoice($xml, $root, $payments, 'payments')))->toBe('');
})->with([
    'absent' => [null],
    'empty'  => [fn (): PaymentsData => PaymentsData::from([])],
]);

it('writes the settled payments variant in schema order', function (): void {
    $payments = PaymentsData::from(['payments' => [
        [
            'paymentMeansCode'      => '10',
            'paymentReference'      => 'REF-1',
            'paymentDate'           => '2026-10-02',
            'paymentAmount'         => '115.00000',
            'payeeFinancialAccount' => ['name' => 'Bank Account', 'nib' => '123456789012345678901'],
        ],
        ['paymentAmount' => '0.5'],
    ]]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(PaymentXmlSerializer::class)->append($xml, $root, $payments, 'payments')))->toBe(
        '<Payments><Payment><PaymentMeansCode>10</PaymentMeansCode><PaymentReference>REF-1</PaymentReference><PaymentDate>2026-10-02</PaymentDate>'
        . '<PaymentAmount>115</PaymentAmount><PayeeFinancialAccount><NIB>123456789012345678901</NIB><Name>Bank Account</Name></PayeeFinancialAccount></Payment>'
        . '<Payment><PaymentAmount>0.5</PaymentAmount></Payment></Payments>',
    );
});

it('writes nothing for settled payments without entries', function (?PaymentsData $payments): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(PaymentXmlSerializer::class)->append($xml, $root, $payments, 'payments')))->toBe('');
})->with([
    'absent' => [null],
    'empty'  => [fn (): PaymentsData => PaymentsData::from([])],
]);

it('writes a transport route with and without the end of each duration', function (): void {
    $route                 = DocumentFixtures::route();
    $route['locations'][1] = [...$route['locations'][1], 'vehicleRegistrationCode' => 'ST-12-AB'];
    $route['locations'][1]['duration'] += ['endDate' => '2026-10-03', 'endTime' => '08:15:00'];

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TransportRouteXmlSerializer::class)
        ->append($xml, $root, TransportRouteData::from($route), 'transportRoute')))->toBe(
            '<TransportRoute><TransportLocation><Address CountryCode="PT"><AddressDetail>Lisbon</AddressDetail></Address>'
            . '<Duration><StartDate>2026-10-02</StartDate><StartTime>13:00:00</StartTime></Duration><TransportModeCode>3</TransportModeCode></TransportLocation>'
            . '<TransportLocation><Address CountryCode="PT"><AddressDetail>Lisbon</AddressDetail></Address>'
            . '<Duration><StartDate>2026-10-02</StartDate><StartTime>13:00:00</StartTime><EndDate>2026-10-03</EndDate><EndTime>08:15:00</EndTime></Duration>'
            . '<TransportModeCode>3</TransportModeCode><VehicleRegistrationCode>ST-12-AB</VehicleRegistrationCode></TransportLocation></TransportRoute>',
        );
});

it('writes a rent receipt in schema order', function (): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(RentReceiptXmlSerializer::class)
        ->append($xml, $root, RentReceiptData::from(DocumentPayloads::rentReceipt()), 'rentReceipt')))->toBe(
            '<RentReceipt><AssetId>HOUSE</AssetId><RentPurposeTypeCode>2</RentPurposeTypeCode><ContractTypeCode>1</ContractTypeCode>'
            . '<RentTypeCode>1</RentTypeCode><ReferencePeriod>2026-10</ReferencePeriod><Address CountryCode="PT"><AddressDetail>Lisbon</AddressDetail></Address></RentReceipt>',
        );
});

it('writes nothing for an absent rent receipt', function (): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): ?DOMElement => resolve(RentReceiptXmlSerializer::class)->append($xml, $root, null, 'rentReceipt')))->toBe('');
});
