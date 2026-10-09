<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;

final class DocumentPayloads
{
    public static function correction(array $overrides = []): array
    {
        return F::payload(['issueReasonCode' => '2', 'references' => F::references(), ...$overrides]);
    }

    public static function returnNote(string $reason): array
    {
        return self::correction(['issueReasonCode' => $reason, 'issueReasonDescription' => 'Goods returned by buyer']);
    }

    public static function transport(array $overrides = []): array
    {
        $payload = F::payload(['transportDocumentTypeCode' => '2', 'transportServiceProvider' => ['reference' => 'EP'], 'transportRoute' => F::route(), ...$overrides]);
        unset($payload['totals']);

        return $payload;
    }

    public static function unpricedTransport(): array
    {
        return self::transport([
            'receiverTypeCode' => '3',
            'receiver'         => null,
            'lines'            => [F::linePayload(['price' => null, 'priceExtension' => null, 'netTotal' => null, 'taxes' => []])],
        ]);
    }

    public static function foreignBuyer(): array
    {
        return ['taxId' => ['value' => '123456789', 'countryCode' => 'PT'], 'name' => 'Foreign buyer'];
    }

    public static function transmission(): array
    {
        return ['transmitterTaxId' => ['value' => '123456789', 'countryCode' => 'CV'], 'software' => ['code' => 'APP', 'name' => 'Fiscal App', 'version' => '1.0']];
    }

    public static function selfBilling(): array
    {
        return ['authorizationId' => '12345678-1234-1234-1234-123456789abc', 'authorizationCode' => '1234'];
    }

    public static function selfBilled(array $payload): array
    {
        $payload['header']['selfBilling'] = self::selfBilling();

        return $payload;
    }

    public static function header(array $overrides = []): array
    {
        return ['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 1, ...$overrides];
    }

    public static function allocatedHeader(array $overrides = []): array
    {
        return self::header([
            'ledCode'             => 99999,
            'serie'               => 'A-1',
            'documentNumber'      => 999999999,
            'innerDocumentNumber' => 'INV-1',
            'isIsolatedAct'       => true,
            ...$overrides,
        ]);
    }

    public static function anonymousSalesReceipt(string $amount, string $tax, string $payable): array
    {
        return F::payload([
            'receiver' => null,
            'payments' => F::payments(),
            'lines'    => [F::linePayload(['price' => $amount, 'priceExtension' => $amount, 'netTotal' => $amount])],
            'totals'   => F::totalsPayload(['priceExtensionTotalAmount' => $amount, 'netTotalAmount' => $amount, 'taxTotalAmount' => $tax, 'payableAmount' => $payable]),
        ]);
    }

    public static function exemptAnonymousSalesReceipt(string $amount): array
    {
        $payload                      = self::anonymousSalesReceipt($amount, '0', $amount);
        $payload['lines'][0]['taxes'] = [['taxTypeCode' => 'NA', 'taxExemptionReasonCode' => '1']];

        return $payload;
    }

    public static function chargedInvoice(string $chargedId, string $chargeTarget): array
    {
        return F::payload([
            'lines'  => [F::linePayload(['id' => $chargedId]), F::linePayload(['lineTypeCode' => 'C', 'lineReferenceId' => $chargeTarget])],
            'totals' => F::totalsPayload(['priceExtensionTotalAmount' => '200', 'netTotalAmount' => '200', 'taxTotalAmount' => '30', 'payableAmount' => '230']),
        ]);
    }

    public static function chargeOnInformationLine(): array
    {
        return F::payload(['lines' => [F::linePayload(['id' => 'A', 'lineTypeCode' => 'I']), F::linePayload(['lineTypeCode' => 'C', 'lineReferenceId' => 'A'])]]);
    }

    public static function duplicateLineIds(string $id): array
    {
        return F::payload(['lines' => [F::linePayload(['id' => $id]), F::linePayload(['id' => $id])]]);
    }

    public static function offlineContingency(array $overrides = []): array
    {
        return ['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 1, 'reasonTypeCode' => '4', ...$overrides];
    }

    public static function contingency(ContingencyReason $reason): array
    {
        return self::offlineContingency(['iuc' => '2026/1', 'reasonTypeCode' => $reason->value, 'reasonDescription' => 'Temporary service interruption']);
    }

    public static function dataObjectGraph(): array
    {
        return [
            'header'   => DocumentHeaderData::from(self::header()),
            'emitter'  => PartyData::from(F::payload()['emitter']),
            'receiver' => PartyData::from(F::payload()['receiver']),
            'lines'    => [],
            'totals'   => F::totals(),
        ];
    }

    public static function rentReceipt(): array
    {
        return [
            'assetId'             => 'HOUSE',
            'rentPurposeTypeCode' => '2',
            'contractTypeCode'    => '1',
            'rentTypeCode'        => '1',
            'referencePeriod'     => '2026-10',
            'address'             => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon'],
        ];
    }

    public static function nestedEvidenceInvoice(): array
    {
        return F::payload([
            'receiver' => ['reference' => 'EP'],
            'lines'    => [F::linePayload([
                'taxes' => [['taxTypeCode' => 'NA', 'taxExemptionReasonCode' => '1']],
                'item'  => ['description' => 'Product', 'emitterIdentification' => 'SKU', 'standardIdentification' => ['ean' => '123456789']],
            ])],
            'totals'     => F::totalsPayload(['taxTotalAmount' => '0', 'payableAmount' => '100']),
            'references' => [['paymentAmount' => '100']],
            'payments'   => ['payeeFinancialAccounts' => [['name' => 'Bank Account', 'nib' => '123456789012345678901']]],
            'emission'   => ['issueMode' => 2, 'contingency' => self::offlineContingency(['reasonTypeCode' => '0', 'reasonDescription' => 'Temporary service interruption'])],
        ]);
    }

    public static function invoiceWithLines(array $lines): array
    {
        $count = \count($lines);

        return F::payload(['lines' => $lines, 'totals' => F::totalsPayload([
            'priceExtensionTotalAmount' => (string) (100 * $count), 'netTotalAmount' => (string) (100 * $count),
            'taxTotalAmount'            => (string) (15 * $count), 'payableAmount' => (string) (115 * $count),
        ])]);
    }
}
