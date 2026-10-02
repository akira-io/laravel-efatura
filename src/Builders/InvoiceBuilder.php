<?php

declare(strict_types=1);

namespace Akira\Efatura\Builders;

use Akira\Efatura\Actions\ValidateIssueDateAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\EmitterConfig;
use Akira\Efatura\Data\DatePeriodData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\FiscalData;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\RentReceiptData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Data\TransportRouteData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Enums\ReceiptType;
use Akira\Efatura\Enums\TransportDocumentType;
use Akira\Efatura\Enums\TransportReceiverType;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Psr\Clock\ClockInterface;

final class InvoiceBuilder
{
    private DocumentType $documentType = DocumentType::Invoice;

    private PartyData|EmitterConfig|null $emitter;

    /** @var array<array-key, mixed> */
    private array $header;

    /** @var list<LineItemData> */
    private array $lines = [];

    /** @var list<ReferenceData> */
    private array $references = [];

    /** @var array<string, FiscalData|BackedEnum|CarbonImmutable|string> */
    private array $sections = [];

    private readonly ValidateIssueDateAction $issueDate;

    public function __construct(EfaturaConfig $config, ClockInterface $clock)
    {
        $now             = CarbonImmutable::instance($clock->now());
        $this->issueDate = new ValidateIssueDateAction($clock);
        $this->emitter   = $config->emitter;
        $this->header    = ['issueDate' => $now, 'issueTime' => $now, 'ledCode' => $config->emitter?->led];
    }

    public function type(DocumentType $type): static
    {
        $this->documentType = $type;

        return $this;
    }

    public function emitter(PartyData $emitter, ?int $ledCode = null): static
    {
        $this->emitter = $emitter;

        return $ledCode === null ? $this : $this->ledCode($ledCode);
    }

    public function ledCode(int $ledCode): static
    {
        $this->header['ledCode'] = $ledCode;

        return $this;
    }

    public function header(DocumentHeaderData $header): static
    {
        $this->header = $header->toPayload();

        return $this;
    }

    public function issuedAt(CarbonInterface $dateTime): static
    {
        $this->header['issueDate'] = $this->header['issueTime'] = $dateTime->toImmutable();

        return $this;
    }

    public function receiver(PartyData $receiver): static
    {
        return $this->section('receiver', $receiver);
    }

    public function line(LineItemData $line): static
    {
        $this->lines[] = $line;

        return $this;
    }

    public function totals(TotalsData $totals): static
    {
        return $this->section('totals', $totals);
    }

    public function reference(ReferenceData $reference): static
    {
        $this->references[] = $reference;

        return $this;
    }

    public function emission(EmissionContextData $emission): static
    {
        return $this->section('emission', $emission);
    }

    public function footer(DocumentFooterData $footer): static
    {
        return $this->section('footer', $footer);
    }

    public function dueDate(CarbonInterface $date): static
    {
        return $this->section('dueDate', $date->toImmutable());
    }

    public function taxPointDate(CarbonInterface $date): static
    {
        return $this->section('taxPointDate', $date->toImmutable());
    }

    public function orderReference(string $reference): static
    {
        return $this->section('orderReference', $reference);
    }

    public function payments(PaymentsData $payments): static
    {
        return $this->section('payments', $payments);
    }

    public function paymentParty(PartyData $party): static
    {
        return $this->section('paymentParty', $party);
    }

    public function delivery(DeliveryData $delivery): static
    {
        return $this->section('delivery', $delivery);
    }

    public function issueReason(IssueReason $reason): static
    {
        return $this->section('issueReasonCode', $reason);
    }

    public function issueReasonDescription(string $description): static
    {
        return $this->section('issueReasonDescription', $description);
    }

    public function rappelPeriod(DatePeriodData $period): static
    {
        return $this->section('rappelPeriod', $period);
    }

    public function receiptType(ReceiptType $type): static
    {
        return $this->section('receiptTypeCode', $type);
    }

    public function rentReceipt(RentReceiptData $rent): static
    {
        return $this->section('rentReceipt', $rent);
    }

    public function receiverType(TransportReceiverType $type): static
    {
        return $this->section('receiverTypeCode', $type);
    }

    public function transportDocumentType(TransportDocumentType $type): static
    {
        return $this->section('transportDocumentTypeCode', $type);
    }

    public function transportServiceProvider(PartyData $provider): static
    {
        return $this->section('transportServiceProvider', $provider);
    }

    public function transportRoute(TransportRouteData $route): static
    {
        return $this->section('transportRoute', $route);
    }

    public function validate(): InvoiceData
    {
        $document = $this->documentType->dataClass()::from([
            'emitter' => $this->emitter instanceof EmitterConfig ? $this->emitter->partyPayload() : $this->emitter,
            ...$this->sections,
            ...array_filter(['lines' => $this->lines, 'references' => $this->references]),
            'header' => $this->header,
        ]);
        $this->issueDate->handle($document->header, $document->emission->issueMode ?? EmissionMode::Online);

        return $document;
    }

    private function section(string $field, FiscalData|BackedEnum|CarbonImmutable|string $value): static
    {
        $this->sections[$field] = $value;

        return $this;
    }
}
