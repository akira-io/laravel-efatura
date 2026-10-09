<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildDocumentXmlAction;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\DocumentXmlGraphs as X;
use Akira\Efatura\Xml\Documents\CreditNoteXmlSerializer;
use Akira\Efatura\Xml\Documents\DebitNoteXmlSerializer;
use Akira\Efatura\Xml\Documents\InvoiceReceiptXmlSerializer;
use Akira\Efatura\Xml\Documents\InvoiceXmlSerializer;
use Akira\Efatura\Xml\Documents\ReceiptXmlSerializer;
use Akira\Efatura\Xml\Documents\RegistrationNoteXmlSerializer;
use Akira\Efatura\Xml\Documents\ReturnNoteXmlSerializer;
use Akira\Efatura\Xml\Documents\SalesReceiptXmlSerializer;
use Akira\Efatura\Xml\Documents\TransportXmlSerializer;
use Akira\Efatura\Xml\DocumentXmlSerializers;
use Akira\Efatura\Xml\XmlWriter;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(X::NOW);
});

dataset('serializers', [
    'FTE' => [DocumentType::Invoice, InvoiceXmlSerializer::class],
    'FRE' => [DocumentType::InvoiceReceipt, InvoiceReceiptXmlSerializer::class],
    'TVE' => [DocumentType::SalesReceipt, SalesReceiptXmlSerializer::class],
    'RCE' => [DocumentType::Receipt, ReceiptXmlSerializer::class],
    'NCE' => [DocumentType::CreditNote, CreditNoteXmlSerializer::class],
    'NDE' => [DocumentType::DebitNote, DebitNoteXmlSerializer::class],
    'DTE' => [DocumentType::Transport, TransportXmlSerializer::class],
    'DVE' => [DocumentType::ReturnNote, ReturnNoteXmlSerializer::class],
    'NLE' => [DocumentType::RegistrationNote, RegistrationNoteXmlSerializer::class],
]);

it('selects the serializer of each document type from the container', function (DocumentType $type, string $serializer): void {
    expect(resolve(DocumentXmlSerializers::class)->for($type))->toBeInstanceOf($serializer);
})->with('serializers');

it('refuses a document of another type', function (DocumentType $type, string $serializer): void {
    $other = X::document($type === DocumentType::Invoice ? DocumentType::DebitNote : DocumentType::Invoice);
    $xml   = new XmlWriter;

    expect(fn () => resolve($serializer)->append($xml, $xml->document('Dfe'), $other))
        ->toThrow(DefinitionException::class, $serializer . ' cannot serialize ' . $other::class . '.');
})->with('serializers');

it('fills every section of the maximal document and writes each one', function (DocumentType $type): void {
    $elements = [
        'header'                   => 'LedCode', 'emitter' => 'EmitterParty', 'receiver' => 'ReceiverParty', 'paymentParty' => 'PaymentParty',
        'transportServiceProvider' => 'TransportServiceProviderParty', 'lines' => 'Lines', 'totals' => 'Totals', 'references' => 'References',
        'payments'                 => 'Payments', 'delivery' => 'Delivery', 'footer' => 'ExtraFields', 'emission' => 'Transmission', 'dueDate' => 'DueDate',
        'orderReference'           => 'OrderReference', 'taxPointDate' => 'TaxPointDate', 'receiptTypeCode' => 'ReceiptTypeCode',
        'rentReceipt'              => 'RentReceipt', 'issueReasonCode' => 'IssueReasonCode', 'issueReasonDescription' => 'IssueReasonDescription',
        'rappelPeriod'             => 'RappelPeriod', 'receiverTypeCode' => 'ReceiverTypeCode', 'transportDocumentTypeCode' => 'TransportDocumentTypeCode',
        'transportRoute'           => 'TransportRoute',
    ];
    $document = X::document($type);
    $dom      = new DOMDocument;
    $dom->loadXML(resolve(BuildDocumentXmlAction::class)->handle($document, X::iud($document), Environment::Test));

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('e', Fiscal::XML_NAMESPACE);

    $sections = array_keys($document->toPayload());
    $filled   = array_keys(array_filter($document->toPayload(), fn (mixed $value): bool => $value !== null && $value !== []));
    $missing  = array_filter($sections, fn (string $section): bool => $xpath->query('/e:Dfe/e:' . $type->xmlElement() . '/e:' . $elements[$section]
        . ' | /e:Dfe/e:' . $elements[$section])->length === 0);

    expect($filled)->toBe($sections)
        ->and($missing)->toBe([]);
})->with(DocumentType::cases());

it('leaves out the optional sections a document does not fill', function (DocumentType $type, array $empty, array $absent): void {
    $document = X::document($type);
    $minimal  = $document::from([...$document->toPayload(), ...$empty]);

    expect(resolve(BuildDocumentXmlAction::class)->handle($minimal, X::iud($document), Environment::Test))
        ->not->toContain(...$absent);
})->with([
    'invoice'     => [DocumentType::Invoice, ['dueDate' => null, 'orderReference' => null, 'taxPointDate' => null, 'payments' => null], ['<DueDate>', '<OrderReference>', '<TaxPointDate>', '<Payments>']],
    'credit note' => [DocumentType::CreditNote, ['rappelPeriod' => null], ['<RappelPeriod>']],
    'transport'   => [DocumentType::Transport, ['receiverTypeCode' => null, 'references' => []], ['<ReceiverTypeCode>', '<References>']],
]);
