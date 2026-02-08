<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum DocumentType: string
{
    case ELECTRONIC_INVOICE = 'FTE';
    case RECEIPT_INVOICE    = 'FRE';
    case SALES_RECEIPT      = 'TVE';
    case CREDIT_NOTE        = 'NCE';
    case TRANSPORT_DOCUMENT = 'DTE';
}
