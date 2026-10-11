<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\LineType;
use Brick\Math\BigDecimal;
use Brick\Money\Money;

final readonly class DfaContentReader
{
    public function read(DocumentData $document, int $documentNumber, bool $withReceiver): DfaContent
    {
        $header = $document->header;
        $totals = $document instanceof HasTotals ? $document->totals : null;

        return new DfaContent(
            self::party($document->emitter),
            $withReceiver && $document->receiver instanceof PartyData ? self::party($document->receiver) : null,
            $document->type()->value,
            $header->series ?? '',
            (string) $documentNumber,
            $header->issueDate->setTimeFrom($header->issueTime),
            array_map(self::line(...), self::lines($document)),
            $totals instanceof TotalsData ? self::totals($totals, \count(self::lines($document))) : null,
            array_map(self::conversion(...), $totals->payableAlternativeAmounts ?? []),
        );
    }

    private static function party(PartyData $party): DfaParty
    {
        return new DfaParty(
            $party->taxId?->value,
            $party->name,
            $party->address?->addressDetail,
            $party->contacts->telephone ?? $party->contacts->mobilephone ?? $party->contacts?->email,
        );
    }

    /**
     * @return list<LineItemData>
     */
    private static function lines(DocumentData $document): array
    {
        if ($document instanceof HasTotals || $document instanceof TransportDocumentData) {
            return $document->lines;
        }

        return [];
    }

    private static function line(LineItemData $line): DfaLine
    {
        return new DfaLine(
            $line->item->emitterIdentification,
            $line->item->description,
            DfaFormat::quantity($line->quantity->value),
            $line->quantity->unitCode,
            $line->price instanceof Money ? DfaFormat::unitPrice($line->price) : '',
            collect($line->taxes)->map(self::tax(...))->implode(' + '),
            self::optionalCve(self::signed($line, $line->netTotal ?? $line->priceExtension)),
        );
    }

    private static function signed(LineItemData $line, ?Money $total): ?Money
    {
        return $line->lineType === LineType::Deduction ? $total?->negated() : $total;
    }

    private static function tax(TaxData $tax): string
    {
        if ($tax->taxPercentage instanceof BigDecimal) {
            return DfaFormat::percentage($tax->taxPercentage);
        }

        return $tax->taxAmount instanceof Money ? DfaFormat::cve($tax->taxAmount) : (string) $tax->taxExemptionReasonCode;
    }

    private static function totals(TotalsData $totals, int $itemCount): DfaTotals
    {
        return new DfaTotals(
            $itemCount,
            DfaFormat::cve($totals->netTotalAmount),
            self::optionalCve($totals->chargeTotalAmount, '0$00'),
            self::optionalCve($totals->discountTotalAmount, '0$00'),
            DfaFormat::cve($totals->taxTotalAmount),
            self::nonZeroCve($totals->withholdingTaxTotalAmount),
            self::nonZeroCve($totals->payableRoundingAmount),
            DfaFormat::cve($totals->payableAmount),
        );
    }

    private static function conversion(PayableAlternativeAmountData $alternative): DfaConversion
    {
        return new DfaConversion(
            $alternative->currencyCode,
            DfaFormat::foreign($alternative->value),
            DfaFormat::rate($alternative->exchangeRate, $alternative->currencyCode),
        );
    }

    private static function optionalCve(?Money $amount, string $absent = ''): string
    {
        return $amount instanceof Money ? DfaFormat::cve($amount) : $absent;
    }

    private static function nonZeroCve(?Money $amount): ?string
    {
        return $amount instanceof Money && ! $amount->isZero() ? DfaFormat::cve($amount) : null;
    }
}
