<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\RegistrationNoteData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\SelfBillingData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Enums\PartyReference;
use Akira\Efatura\Enums\ReceiptType;
use Akira\Efatura\Enums\TransportReceiverType;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\FiscalRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class ValidateDocumentCompatibilityAction
{
    public function __construct(private ValidateIssueDateAction $dates, private ReconcileDocumentTotalsAction $totals) {}

    public function handle(InvoiceData $document): void
    {
        $document->emitter->validateEmitter();
        $this->dates->handle($document->header, $document->emission->issueMode ?? EmissionMode::Online);
        Validator::make(['receiverReference' => $document->receiver?->reference?->value], ['receiverReference' => ['nullable', Rule::in([PartyReference::Emitter->value])]])->validate();
        if ($document->header->selfBilling instanceof SelfBillingData) {
            Validator::make(['receiver' => $document->receiver], ['receiver' => ['required']])->validate();
        }

        if ($document instanceof CreditNoteData || $document instanceof DebitNoteData || $document instanceof ReturnNoteData || $document instanceof ReceiptData) {
            Validator::make(['references' => $document->references], ['references' => ['required', 'array', 'min:1']])->validate();
        }

        if ($document instanceof CreditNoteData || $document instanceof DebitNoteData || $document instanceof ReturnNoteData) {
            $allowed = ['2', '3', '6', '8', '9', 'IN', ...match (true) {
                $document instanceof CreditNoteData => ['7', 'DRP'],
                $document instanceof DebitNoteData  => ['4', 'DD'],
                default                             => ['7', '0'],
            }];
            Validator::make(['issueReasonCode' => $document->issueReasonCode->value], ['issueReasonCode' => [Rule::in($allowed)]])->validate();
        }

        if ($document instanceof ReturnNoteData) {
            Validator::make(['issueReasonDescription' => $document->issueReasonDescription], ['issueReasonDescription' => ['nullable', Rule::requiredIf($document->issueReasonCode === IssueReason::Other), ...FiscalRules::text(10, 500)]])->validate();
        }

        if ($document instanceof ReceiptData) {
            Validator::make(['rentReceipt' => $document->rentReceipt], ['rentReceipt' => [Rule::requiredIf($document->receiptTypeCode === ReceiptType::Rent), Rule::prohibitedIf($document->receiptTypeCode !== ReceiptType::Rent)]])->validate();
        }

        if ($document instanceof TransportDocumentData) {
            Validator::make(['receiver' => $document->receiver], ['receiver' => [Rule::requiredIf($document->receiverTypeCode !== TransportReceiverType::Undetermined), Rule::prohibitedIf($document->receiverTypeCode === TransportReceiverType::Undetermined)]])->validate();
            if ($document->receiverTypeCode === TransportReceiverType::Taxpayer) {
                $receiver = $document->receiver?->reference === PartyReference::Emitter ? $document->emitter : $document->receiver;
                Validator::make(['receiver' => ['taxId' => ['countryCode' => $receiver?->taxId?->countryCode]]], ['receiver.taxId.countryCode' => ['required', 'in:CV']])->validate();
            }

            if ($document->transportServiceProvider->reference === PartyReference::Receiver) {
                Validator::make(['receiver' => $document->receiver], ['receiver' => ['required']])->validate();
            }
        }

        if ($document instanceof ElectronicInvoiceData || $document instanceof ReceiptInvoiceData) {
            Validator::make(
                ['orderReference' => $document->orderReference, 'taxPointDate' => $document->taxPointDate?->format('Y-m-d')],
                ['orderReference' => ['nullable', ...FiscalRules::code()], 'taxPointDate' => ['nullable', new FiscalDate, 'before_or_equal:' . $document->header->issueDate->format('Y-m-d')]],
            )->validate();
        }

        if ($document instanceof ElectronicInvoiceData) {
            Validator::make(['dueDate' => $document->dueDate], ['dueDate' => ['nullable', new FiscalDate]])->validate();
        }

        if ($document instanceof ElectronicInvoiceData || $document instanceof ReceiptInvoiceData || $document instanceof SalesReceiptData || $document instanceof ReceiptData || $document instanceof RegistrationNoteData) {
            if ($document->payments instanceof PaymentsData) {
                $payments = $document->payments;
                $rules    = $document instanceof ElectronicInvoiceData ? ['payments' => ['prohibited']] : [
                    'payments' => ['required', 'array', 'min:1'], 'paymentDueDate' => ['prohibited'], 'paymentTerms' => ['prohibited'], 'payeeFinancialAccounts' => ['prohibited'],
                ];
                Validator::make(['payments' => $payments->payments, 'paymentDueDate' => $payments->paymentDueDate,
                    'paymentTerms'          => $payments->paymentTerms, 'payeeFinancialAccounts' => $payments->payeeFinancialAccounts], $rules)->validate();
                if ($document instanceof ReceiptInvoiceData) {
                    foreach ($payments->payments as $payment) {
                        Validator::make(['paymentDate' => $payment->paymentDate?->format('Y-m-d')], ['paymentDate' => ['nullable', 'in:' . $document->header->issueDate->format('Y-m-d')]])->validate();
                    }
                }
            }
        }

        if ($document instanceof ElectronicInvoiceData || $document instanceof ReceiptInvoiceData || $document instanceof SalesReceiptData || $document instanceof CreditNoteData || $document instanceof DebitNoteData || $document instanceof ReturnNoteData || $document instanceof RegistrationNoteData || $document instanceof TransportDocumentData) {
            $this->lines($document->lines, $document instanceof TransportDocumentData, $document instanceof CreditNoteData || $document instanceof ReturnNoteData);
            if (! $document instanceof TransportDocumentData) {
                $this->totals->handle($document->lines, $document->totals);
            }
        }

        if ($document instanceof SalesReceiptData && $document->totals->payableAmount->getAmount()->isGreaterThanOrEqualTo('20000')) {
            Validator::make(['receiver' => $document->receiver], ['receiver' => ['required']])->validate();
        }
    }

    /**
     * @param list<LineItemData> $lines
     */
    private function lines(array $lines, bool $transport, bool $optionalTax): void
    {
        $ids = collect($lines)->map(static fn (LineItemData $line): ?string => $line->id)->filter(static fn (?string $id): bool => $id !== null)->values();
        Validator::make(['ids' => $ids->all()], ['ids.*' => ['distinct:strict']])->validate();
        foreach ($lines as $index => $line) {
            $rules = ['lineReferenceId' => ['nullable', Rule::in($ids->all())]];
            if (! $transport) {
                $rules += ['price' => ['required'], 'priceExtension' => ['required'], 'netTotal' => ['required'], 'taxes' => [Rule::requiredIf(! $optionalTax)]];
            }

            Validator::make(['lineReferenceId' => $line->lineReferenceId, 'price' => $line->price, 'priceExtension' => $line->priceExtension, 'netTotal' => $line->netTotal, 'taxes' => $line->taxes], $rules)->validate();
            if ($line->lineTypeCode === LineType::Charge) {
                $target = collect($lines)->first(fn (LineItemData $candidate): bool => $candidate->id === $line->lineReferenceId);
                Validator::make(['lines' => [$index => ['lineReferenceId' => $target?->lineTypeCode->value]]], ['lines.' . $index . '.lineReferenceId' => ['required', 'in:N']])->validate();
            }
        }
    }
}
