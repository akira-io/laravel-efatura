<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Builders\DocumentBuilder;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\TransportRouteData;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Enums\ReceiptType;
use Akira\Efatura\Enums\TransportDocumentType;
use Akira\Efatura\Enums\TransportReceiverType;

final class DocumentGraphs
{
    public static function invoiced(DocumentBuilder $draft): DocumentBuilder
    {
        return $draft->receiver(BuilderFixtures::receiver())->line(DocumentFixtures::line())->totals(DocumentFixtures::totals());
    }

    public static function paidInvoice(DocumentBuilder $draft): DocumentBuilder
    {
        return self::paid(self::invoiced($draft));
    }

    public static function salesReceipt(DocumentBuilder $draft): DocumentBuilder
    {
        return self::paid($draft->line(DocumentFixtures::line())->totals(DocumentFixtures::totals()));
    }

    public static function receipt(DocumentBuilder $draft): DocumentBuilder
    {
        return self::paid(self::referred($draft->receiver(BuilderFixtures::receiver()))->receiptType(ReceiptType::Commercial));
    }

    public static function correction(DocumentBuilder $draft): DocumentBuilder
    {
        return self::referred(self::invoiced($draft))->issueReason(IssueReason::Article65Paragraph2);
    }

    public static function returnNote(DocumentBuilder $draft): DocumentBuilder
    {
        return self::correction($draft)->issueReasonDescription('Goods returned by buyer');
    }

    public static function registrationNote(DocumentBuilder $draft): DocumentBuilder
    {
        return self::paid(self::referred(self::invoiced($draft)));
    }

    public static function transport(DocumentBuilder $draft): DocumentBuilder
    {
        return self::referred($draft->receiver(BuilderFixtures::receiver())->line(DocumentFixtures::line()))
            ->transportDocumentType(TransportDocumentType::Dispatch)
            ->transportServiceProvider(PartyData::from(['reference' => 'EP']))
            ->transportRoute(TransportRouteData::from(DocumentFixtures::route()))
            ->receiverType(TransportReceiverType::Taxpayer);
    }

    private static function referred(DocumentBuilder $draft): DocumentBuilder
    {
        return $draft->reference(ReferenceData::from(DocumentFixtures::references()[0]));
    }

    private static function paid(DocumentBuilder $draft): DocumentBuilder
    {
        return $draft->payments(PaymentsData::from(DocumentFixtures::payments()));
    }
}
