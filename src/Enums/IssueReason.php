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
}
