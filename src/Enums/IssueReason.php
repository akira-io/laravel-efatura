<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum IssueReason: string
{
    case Other               = '0';
    case Article65Paragraph2 = '2';
    case Article65Paragraph3 = '3';
    case Article65Paragraph4 = '4';
    case Article65Paragraph6 = '6';
    case Article65Paragraph7 = '7';
    case Article65Paragraph8 = '8';
    case Article65Paragraph9 = '9';
    case ExpenseDebit        = 'DD';
    case Unavailable         = 'IN';
    case RappelDiscount      = 'DRP';

    /**
     * @return list<self>
     */
    public static function allowedFor(DocumentType $type): array
    {
        $corrections = [self::Article65Paragraph2, self::Article65Paragraph3, self::Article65Paragraph6, self::Article65Paragraph8, self::Article65Paragraph9, self::Unavailable];

        return match ($type) {
            DocumentType::CreditNote => [...$corrections, self::Article65Paragraph7, self::RappelDiscount],
            DocumentType::DebitNote  => [...$corrections, self::Article65Paragraph4, self::ExpenseDebit],
            DocumentType::ReturnNote => [...$corrections, self::Article65Paragraph7, self::Other],
            DocumentType::Invoice, DocumentType::InvoiceReceipt, DocumentType::SalesReceipt, DocumentType::Receipt,
            DocumentType::Transport, DocumentType::RegistrationNote => [],
        };
    }
}
