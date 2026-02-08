<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum DocumentType: string
{
    case ELECTRONIC_INVOICE            = 'FTE';
    case ELECTRONIC_INVOICE_RECEIPT    = 'FRE';
    case ELECTRONIC_SALES_TICKET       = 'TVE';
    case ELECTRONIC_RECEIPT            = 'RCE';
    case ELECTRONIC_CREDIT_NOTE        = 'NCE';
    case ELECTRONIC_DEBIT_NOTE         = 'NDE';
    case ELECTRONIC_TRANSPORT_DOCUMENT = 'DTE';
    case ELECTRONIC_RETURN_NOTE        = 'DVE';
    case ELECTRONIC_ENTRY_NOTE         = 'NLE';

    /**
     * @return array<int, self>
     */
    public static function supported(): array
    {
        return self::cases();
    }

    public function isSupported(): bool
    {
        return true;
    }
}
