<?php

declare(strict_types=1);

namespace Akira\Efatura\Builders;

use Akira\Efatura\Actions\ValidateIssueDateAction;
use Akira\Efatura\Builders\Concerns\HasDocumentSections;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\RegistrationNoteData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Psr\Clock\ClockInterface;

final class InvoiceBuilder
{
    use HasDocumentSections;

    private DocumentType $documentType = DocumentType::Invoice;

    /** @var array<string, mixed> */
    private array $draft;

    /** @var array<array-key, mixed> */
    private array $header;

    /** @var list<array<array-key, mixed>> */
    private array $lines = [];

    /** @var list<array<array-key, mixed>> */
    private array $references = [];

    private readonly ValidateIssueDateAction $issueDate;

    public function __construct(EfaturaConfig $config, ClockInterface $clock)
    {
        $now             = Fiscal::local(CarbonImmutable::instance($clock->now()));
        $this->issueDate = new ValidateIssueDateAction($clock);
        $this->draft     = ['emitter' => ConfiguredEmitter::party($config->emitter)];
        $this->header    = ['issueDate' => $now->format(Fiscal::DATE_FORMAT), 'issueTime' => $now->format(Fiscal::TIME_FORMAT), 'ledCode' => $config->emitter?->led];
    }

    public function type(DocumentType $type): self
    {
        $this->documentType = $type;

        return $this;
    }

    public function emitter(PartyData $emitter, ?int $ledCode = null): self
    {
        $this->draft['emitter']  = $emitter->toArray();
        $this->header['ledCode'] = $ledCode;

        return $this;
    }

    public function ledCode(int $ledCode): self
    {
        $this->header['ledCode'] = $ledCode;

        return $this;
    }

    public function header(DocumentHeaderData $header): self
    {
        $this->header = $header->toArray();

        return $this;
    }

    public function issuedAt(CarbonInterface $dateTime): self
    {
        $local                     = Fiscal::local($dateTime);
        $this->header['issueDate'] = $local->format(Fiscal::DATE_FORMAT);
        $this->header['issueTime'] = $local->format(Fiscal::TIME_FORMAT);

        return $this;
    }

    public function receiver(PartyData $receiver): self
    {
        $this->draft['receiver'] = $receiver->toArray();

        return $this;
    }

    public function line(LineItemData $line): self
    {
        $this->lines[]        = $line->toArray();
        $this->draft['lines'] = $this->lines;

        return $this;
    }

    public function totals(TotalsData $totals): self
    {
        $this->draft['totals'] = $totals->toArray();

        return $this;
    }

    public function reference(ReferenceData $reference): self
    {
        $this->references[]        = $reference->toArray();
        $this->draft['references'] = $this->references;

        return $this;
    }

    public function emission(EmissionContextData $emission): self
    {
        $this->draft['emission'] = $emission->toArray();

        return $this;
    }

    public function footer(DocumentFooterData $footer): self
    {
        $this->draft['footer'] = $footer->toArray();

        return $this;
    }

    public function validate(): InvoiceData
    {
        $class = match ($this->documentType) {
            DocumentType::Invoice          => ElectronicInvoiceData::class,
            DocumentType::InvoiceReceipt   => ReceiptInvoiceData::class,
            DocumentType::SalesReceipt     => SalesReceiptData::class,
            DocumentType::Receipt          => ReceiptData::class,
            DocumentType::CreditNote       => CreditNoteData::class,
            DocumentType::DebitNote        => DebitNoteData::class,
            DocumentType::ReturnNote       => ReturnNoteData::class,
            DocumentType::RegistrationNote => RegistrationNoteData::class,
            DocumentType::Transport        => TransportDocumentData::class,
        };

        $document = $class::validateAndCreate([...$this->draft, 'header' => $this->header]);
        $this->issueDate->handle($document->header, $document->emission->issueMode ?? EmissionMode::Online);

        return $document;
    }
}
