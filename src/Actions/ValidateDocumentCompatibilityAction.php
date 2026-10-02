<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\Contracts\HasTaxPointDate;
use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Data\Contracts\SettlesOnIssue;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\PaymentData;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class ValidateDocumentCompatibilityAction
{
    public function __construct(private VerifyDocumentTotalsAction $totals) {}

    public function handle(InvoiceData $document): void
    {
        if ($document instanceof HasTotals) {
            $this->totals->handle($document->lines, $document->totals);
        }

        if ($document instanceof HasTaxPointDate && $document->taxPointDate?->isAfter($document->header->issueDate)) {
            $this->fail('taxPointDate', 'tax_point_after_issue');
        }

        if ($document instanceof SettlesOnIssue) {
            $this->paidOnIssueDay($document, $document->payments->payments);
        }
    }

    /**
     * @param list<PaymentData> $payments
     */
    private function paidOnIssueDay(InvoiceData $document, array $payments): void
    {
        $late = collect($payments)->search(
            static fn (PaymentData $payment): bool => $payment->paymentDate instanceof CarbonImmutable && ! $payment->paymentDate->isSameDay($document->header->issueDate),
        );

        if (\is_int($late)) {
            $this->fail('payments.payments.' . $late . '.paymentDate', 'payment_not_on_issue_day');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => __('efatura::efatura.validation.' . $message)]);
    }
}
