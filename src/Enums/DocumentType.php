<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

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

    public function xmlElement(): string
    {
        return $this->name;
    }
}
