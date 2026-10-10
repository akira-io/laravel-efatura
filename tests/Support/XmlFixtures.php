<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Actions\BuildDocumentXmlAction;
use Akira\Efatura\Actions\BuildEventXmlAction;
use Akira\Efatura\Builders\DocumentBuilder;
use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentGraphs as G;

final class XmlFixtures
{
    public static function minimal(DocumentType $type): DocumentData
    {
        $draft = Efatura::invoice()->type($type)->emitter(BuilderFixtures::emitter())
            ->header(DocumentHeaderData::from(DocumentPayloads::header(['serie' => 'A', 'documentNumber' => 1])))
            ->emission(EmissionContextData::from(DocumentPayloads::transmission()));

        return self::required($type, $draft)->build();
    }

    public static function maximal(DocumentType $type, array $overrides = []): DocumentData
    {
        $document = DocumentXmlGraphs::document($type);
        $payload  = [...$document->toArray(), 'emitter' => self::fullEmitter()];

        if ($document instanceof HasTotals || $document instanceof TransportDocumentData) {
            $payload['lines'] = self::lines();
        }

        if ($document instanceof HasTotals) {
            $payload['totals'] = self::totals();
        }

        return $type->dataClass()::from(array_replace($payload, $overrides));
    }

    public static function documentXml(DocumentData $document, Environment $repository = Environment::Test, bool $isSpecimen = false): string
    {
        $iud = DocumentXmlGraphs::iud($document, ['repositoryCode' => $repository->value]);

        return resolve(BuildDocumentXmlAction::class)->handle($document, $iud, $repository, $isSpecimen);
    }

    public static function eventXml(array $payload): string
    {
        return resolve(BuildEventXmlAction::class)->handle(EventData::from(EventFixtures::transmitted($payload)), EventFixtures::eventId(), Environment::Test);
    }

    public static function offline(): array
    {
        return self::contingent(2, DocumentPayloads::offlineContingency());
    }

    public static function off(): array
    {
        return self::contingent(3, DocumentPayloads::contingency(ContingencyReason::PowerFailure));
    }

    private static function contingent(int $issueMode, array $contingency): array
    {
        return ['emission' => [...DocumentPayloads::transmission(), 'issueMode' => $issueMode, 'contingency' => $contingency]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function lines(): array
    {
        $vat    = ['taxTypeCode' => 'IVA', 'taxPercentage' => '15'];
        $charge = [
            ...XmlPartFixtures::chargeLine(),
            'quantity'       => ['value' => '2', 'unitCode' => 'C62', 'isStandardUnitCode' => true],
            'price'          => '25',
            'priceExtension' => '50',
            'discount'       => ['value' => '20', 'valueType' => 'P'],
            'netTotal'       => '36',
            'taxes'          => [[...$vat, 'taxTotal' => '5.4']],
        ];

        return [
            F::linePayload(['id' => 'L1', 'netTotal' => '90', 'taxes' => [$vat, ['taxTypeCode' => 'IR', 'taxPercentage' => '10']]]),
            $charge,
            F::linePayload(['lineTypeCode' => 'D', 'id' => 'L3', 'price' => '20', 'priceExtension' => '20', 'netTotal' => '20']),
            F::linePayload(['lineTypeCode' => 'I', 'id' => 'L4', 'price' => '10', 'priceExtension' => '10', 'netTotal' => '9']),
        ];
    }

    private static function totals(): array
    {
        return [
            ...XmlPartFixtures::fullTotals(),
            'priceExtensionTotalAmount' => '130',
            'chargeTotalAmount'         => '50',
            'discountTotalAmount'       => '30',
            'netTotalAmount'            => '106',
            'discount'                  => ['value' => '10', 'valueType' => 'P'],
            'taxTotalAmount'            => '17.25',
            'withholdingTaxTotalAmount' => '9',
            'payableRoundingAmount'     => '-0.25',
            'payableAmount'             => '114',
        ];
    }

    private static function fullEmitter(): array
    {
        return [...F::payload()['emitter'], 'address' => XmlPartFixtures::fullAddress(), 'contacts' => [
            'telephone' => '1234567', 'mobilephone' => '7654321', 'telefax' => '1234568', 'email' => 'emitter@example.cv', 'website' => 'https://example.cv',
        ]];
    }

    private static function required(DocumentType $type, DocumentBuilder $draft): DocumentBuilder
    {
        return match ($type) {
            DocumentType::Invoice          => G::invoiced($draft),
            DocumentType::InvoiceReceipt   => G::paidInvoice($draft),
            DocumentType::SalesReceipt     => G::salesReceipt($draft),
            DocumentType::Receipt          => G::receipt($draft),
            DocumentType::CreditNote       => G::correction($draft),
            DocumentType::DebitNote        => G::correction($draft),
            DocumentType::Transport        => G::transport($draft),
            DocumentType::ReturnNote       => G::returnNote($draft),
            DocumentType::RegistrationNote => G::registrationNote($draft),
        };
    }
}
