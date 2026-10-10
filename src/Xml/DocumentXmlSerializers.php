<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use Akira\Efatura\Contracts\DocumentXmlSerializer;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Xml\Documents\CreditNoteXmlSerializer;
use Akira\Efatura\Xml\Documents\DebitNoteXmlSerializer;
use Akira\Efatura\Xml\Documents\InvoiceReceiptXmlSerializer;
use Akira\Efatura\Xml\Documents\InvoiceXmlSerializer;
use Akira\Efatura\Xml\Documents\ReceiptXmlSerializer;
use Akira\Efatura\Xml\Documents\RegistrationNoteXmlSerializer;
use Akira\Efatura\Xml\Documents\ReturnNoteXmlSerializer;
use Akira\Efatura\Xml\Documents\SalesReceiptXmlSerializer;
use Akira\Efatura\Xml\Documents\TransportXmlSerializer;
use Illuminate\Contracts\Container\Container;

final readonly class DocumentXmlSerializers
{
    public function __construct(private Container $container) {}

    public function for(DocumentType $type): DocumentXmlSerializer
    {
        return $this->container->make(match ($type) {
            DocumentType::Invoice          => InvoiceXmlSerializer::class,
            DocumentType::InvoiceReceipt   => InvoiceReceiptXmlSerializer::class,
            DocumentType::SalesReceipt     => SalesReceiptXmlSerializer::class,
            DocumentType::Receipt          => ReceiptXmlSerializer::class,
            DocumentType::CreditNote       => CreditNoteXmlSerializer::class,
            DocumentType::DebitNote        => DebitNoteXmlSerializer::class,
            DocumentType::Transport        => TransportXmlSerializer::class,
            DocumentType::ReturnNote       => ReturnNoteXmlSerializer::class,
            DocumentType::RegistrationNote => RegistrationNoteXmlSerializer::class,
        });
    }
}
