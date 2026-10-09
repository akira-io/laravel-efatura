<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Actions\BuildIudAction;
use Akira\Efatura\Builders\DocumentBuilder;
use Akira\Efatura\Data\DatePeriodData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\IudData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\RentReceiptData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Enums\ReceiptType;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Tests\Support\DocumentGraphs as G;
use Carbon\CarbonImmutable;

final class DocumentXmlGraphs
{
    public const string NOW = '2026-10-02T12:00:00-01:00';

    public const string RANDOM_CODE = '1234567890';

    public static function document(DocumentType $type): DocumentData
    {
        $draft = Efatura::invoice()->type($type)->emitter(BuilderFixtures::emitter())
            ->header(DocumentHeaderData::from(DocumentPayloads::allocatedHeader(['selfBilling' => DocumentPayloads::selfBilling()])))
            ->emission(EmissionContextData::from(DocumentPayloads::transmission()))
            ->footer(DocumentFooterData::from(['note' => 'Customer delivery note', 'extraFields' => [
                ['name' => 'CustomerHint', 'value' => 'Ready'],
                ['name' => 'Route', 'value' => 'North', 'namespace' => 'urn:example:fields'],
            ]]));

        return self::complete($type, $draft)->build();
    }

    public static function iud(DocumentData $document, array $overrides = []): string
    {
        return resolve(BuildIudAction::class)->handle(IudData::from([
            'repositoryCode'   => Environment::Test->value,
            'issueDate'        => $document->header->issueDate,
            'emitterTaxId'     => $document->emitter->taxId?->value,
            'ledCode'          => $document->header->ledCode,
            'documentTypeCode' => $document->type()->value,
            'documentNumber'   => $document->header->documentNumber,
            'randomCode'       => self::RANDOM_CODE,
            ...$overrides,
        ]));
    }

    public static function fixture(DocumentType $type): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/xml/documents/' . $type->value . '.xml');
    }

    private static function complete(DocumentType $type, DocumentBuilder $draft): DocumentBuilder
    {
        return match ($type) {
            DocumentType::Invoice => G::invoiced($draft)->dueDate(new CarbonImmutable('2026-10-31'))->orderReference('ORDER-1')
                ->taxPointDate(new CarbonImmutable('2026-10-01'))->reference(self::reference())->delivery(self::delivery())
                ->payments(PaymentsData::from(['paymentDueDate' => '2026-10-31', 'paymentTerms' => ['note' => 'Thirty days net'],
                    'payeeFinancialAccounts'                    => [['name' => 'Bank Account', 'nib' => '123456789012345678901']]])),
            DocumentType::InvoiceReceipt => G::paidInvoice($draft)->orderReference('ORDER-1')->taxPointDate(new CarbonImmutable('2026-10-01'))
                ->paymentParty(self::payer())->reference(self::reference())->delivery(self::delivery()),
            DocumentType::SalesReceipt => G::salesReceipt($draft)->receiver(BuilderFixtures::receiver())->delivery(self::delivery()),
            DocumentType::Receipt      => G::receipt($draft)->receiptType(ReceiptType::Rent)->paymentParty(self::payer())
                ->rentReceipt(RentReceiptData::from(DocumentPayloads::rentReceipt())),
            DocumentType::CreditNote => G::correction($draft)->issueReason(IssueReason::RappelDiscount)
                ->rappelPeriod(DatePeriodData::from(['startDate' => '2026-09-01', 'endDate' => '2026-09-30'])),
            DocumentType::DebitNote        => G::correction($draft),
            DocumentType::Transport        => G::transport($draft),
            DocumentType::ReturnNote       => G::returnNote($draft),
            DocumentType::RegistrationNote => G::registrationNote($draft)->paymentParty(self::payer()),
        };
    }

    private static function reference(): ReferenceData
    {
        return ReferenceData::from(['fiscalDocument' => ['value' => IdentifierFixtures::NODE_IUD], 'paymentAmount' => '115']);
    }

    private static function delivery(): DeliveryData
    {
        return DeliveryData::from(['deliveryDate' => '2026-10-02', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon']]);
    }

    private static function payer(): PartyData
    {
        return PartyData::from(['reference' => 'RP']);
    }
}
