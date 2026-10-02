<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\TotalsData;

final class DocumentFixtures
{
    public static function payload(array $overrides = []): array
    {
        return array_replace([
            'header'  => ['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 1],
            'emitter' => ['taxId' => ['value' => '100200300', 'countryCode' => 'CV'], 'name' => 'Emitter',
                'address'         => ['countryCode' => 'CV', 'addressDetail' => 'Praia office', 'addressCode' => 'CV111111111011110101'],
                'contacts'        => ['email' => 'emitter@example.cv', 'telephone' => '1234567']],
            'receiver' => ['taxId' => ['value' => '900800700', 'countryCode' => 'CV'], 'name' => 'Receiver'],
            'lines'    => [self::linePayload()], 'totals' => self::totalsPayload(),
        ], $overrides);
    }

    public static function linePayload(array $overrides = []): array
    {
        return array_replace(['quantity' => ['value' => '1', 'unitCode' => 'C62'],
            'item'                       => ['description' => 'Product', 'emitterIdentification' => 'SKU'],
            'price'                      => '100', 'priceExtension' => '100', 'netTotal' => '100',
            'taxes'                      => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15']]], $overrides);
    }

    public static function totalsPayload(array $overrides = []): array
    {
        return array_replace(['priceExtensionTotalAmount' => '100', 'netTotalAmount' => '100',
            'taxTotalAmount'                              => '15', 'payableAmount' => '115'], $overrides);
    }

    public static function line(array $overrides = []): LineItemData
    {
        return LineItemData::from(self::linePayload($overrides));
    }

    public static function totals(array $overrides = []): TotalsData
    {
        return TotalsData::from(self::totalsPayload($overrides));
    }

    public static function references(): array
    {
        return [['fiscalDocument' => ['value' => '1/2026/A/1', 'isOldDocument' => true]]];
    }

    public static function payments(): array
    {
        return ['payments' => [['paymentMeansCode' => '10', 'paymentAmount' => '115']]];
    }

    public static function receiptPayload(string $receiptTypeCode): array
    {
        $payload = self::payload(['receiptTypeCode' => $receiptTypeCode, 'references' => self::references(), 'payments' => self::payments()]);
        unset($payload['lines'], $payload['totals']);

        return $payload;
    }

    public static function route(): array
    {
        $location = ['address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon'],
            'duration'         => ['startDate' => '2026-10-02', 'startTime' => '13:00:00'], 'transportModeCode' => '3'];

        return ['locations' => [$location, $location]];
    }
}
