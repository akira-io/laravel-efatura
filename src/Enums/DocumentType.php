<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\RegistrationNoteData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Exceptions\DefinitionException;

enum DocumentType: string
{
    case Invoice          = 'FTE';
    case InvoiceReceipt   = 'FRE';
    case SalesReceipt     = 'TVE';
    case Receipt          = 'RCE';
    case CreditNote       = 'NCE';
    case DebitNote        = 'NDE';
    case Transport        = 'DTE';
    case ReturnNote       = 'DVE';
    case RegistrationNote = 'NLE';

    public function code(): int
    {
        return match ($this) {
            self::Invoice          => 1,
            self::InvoiceReceipt   => 2,
            self::SalesReceipt     => 3,
            self::Receipt          => 4,
            self::CreditNote       => 5,
            self::DebitNote        => 6,
            self::Transport        => 7,
            self::ReturnNote       => 8,
            self::RegistrationNote => 9,
        };
    }

    /**
     * @param class-string<DocumentData> $dataClass
     */
    public static function fromDataClass(string $dataClass): self
    {
        return collect(self::cases())->first(static fn (self $type): bool => $type->dataClass() === $dataClass)
            ?? throw DefinitionException::documentClass($dataClass);
    }

    /**
     * @return class-string<DocumentData>
     */
    public function dataClass(): string
    {
        return match ($this) {
            self::Invoice          => ElectronicInvoiceData::class,
            self::InvoiceReceipt   => ReceiptInvoiceData::class,
            self::SalesReceipt     => SalesReceiptData::class,
            self::Receipt          => ReceiptData::class,
            self::CreditNote       => CreditNoteData::class,
            self::DebitNote        => DebitNoteData::class,
            self::Transport        => TransportDocumentData::class,
            self::ReturnNote       => ReturnNoteData::class,
            self::RegistrationNote => RegistrationNoteData::class,
        };
    }

    public function requiresLinePricing(): bool
    {
        return $this !== self::Transport;
    }

    public function requiresLineTaxes(): bool
    {
        return ! \in_array($this, [self::CreditNote, self::ReturnNote, self::Transport], true);
    }

    public function xmlElement(): string
    {
        return match ($this) {
            self::Invoice          => 'Invoice',
            self::InvoiceReceipt   => 'InvoiceReceipt',
            self::SalesReceipt     => 'SalesReceipt',
            self::Receipt          => 'Receipt',
            self::CreditNote       => 'CreditNote',
            self::DebitNote        => 'DebitNote',
            self::Transport        => 'Transport',
            self::ReturnNote       => 'ReturnNote',
            self::RegistrationNote => 'RegistrationNote',
        };
    }
}
