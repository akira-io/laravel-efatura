<?php

declare(strict_types=1);

namespace Akira\Efatura\Builders\Concerns;

use Akira\Efatura\Data\DatePeriodData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\RentReceiptData;
use Akira\Efatura\Data\TransportRouteData;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Enums\ReceiptType;
use Akira\Efatura\Enums\TransportDocumentType;
use Akira\Efatura\Enums\TransportReceiverType;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonInterface;

trait HasDocumentSections
{
    public function dueDate(CarbonInterface $date): self
    {
        $this->draft['dueDate'] = $date->format(Fiscal::DATE_FORMAT);

        return $this;
    }

    public function taxPointDate(CarbonInterface $date): self
    {
        $this->draft['taxPointDate'] = $date->format(Fiscal::DATE_FORMAT);

        return $this;
    }

    public function orderReference(string $reference): self
    {
        $this->draft['orderReference'] = $reference;

        return $this;
    }

    public function payments(PaymentsData $payments): self
    {
        $this->draft['payments'] = $payments->toArray();

        return $this;
    }

    public function paymentParty(PartyData $party): self
    {
        $this->draft['paymentParty'] = $party->toArray();

        return $this;
    }

    public function delivery(DeliveryData $delivery): self
    {
        $this->draft['delivery'] = $delivery->toArray();

        return $this;
    }

    public function issueReason(IssueReason $reason): self
    {
        $this->draft['issueReasonCode'] = $reason->value;

        return $this;
    }

    public function issueReasonDescription(string $description): self
    {
        $this->draft['issueReasonDescription'] = $description;

        return $this;
    }

    public function rappelPeriod(DatePeriodData $period): self
    {
        $this->draft['rappelPeriod'] = $period->toArray();

        return $this;
    }

    public function receiptType(ReceiptType $type): self
    {
        $this->draft['receiptTypeCode'] = $type->value;

        return $this;
    }

    public function rentReceipt(RentReceiptData $rent): self
    {
        $this->draft['rentReceipt'] = $rent->toArray();

        return $this;
    }

    public function receiverType(TransportReceiverType $type): self
    {
        $this->draft['receiverTypeCode'] = $type->value;

        return $this;
    }

    public function transportDocumentType(TransportDocumentType $type): self
    {
        $this->draft['transportDocumentTypeCode'] = $type->value;

        return $this;
    }

    public function transportServiceProvider(PartyData $provider): self
    {
        $this->draft['transportServiceProvider'] = $provider->toArray();

        return $this;
    }

    public function transportRoute(TransportRouteData $route): self
    {
        $this->draft['transportRoute'] = $route->toArray();

        return $this;
    }
}
