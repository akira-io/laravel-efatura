<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Actions\BuildIudAction;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\IudData;
use Akira\Efatura\Data\SelfBillingAuthorizationRequestData;

final class SelfBillingFixtures
{
    public const string SELLER = '200300400';

    public const string BUYER = '100200300';

    public const string AUTHORIZATION_ID = '3f2b9c1e-8d4a-4f6b-9a7e-1c2d3e4f5a6b';

    /**
     * @return array<string, mixed>
     */
    public static function input(array $overrides = []): array
    {
        return ['taxId' => self::SELLER, 'documentTypeCode' => 'FTE', 'mobilePhoneNumber' => '9912345', 'totalAmount' => '1500.50', ...$overrides];
    }

    public static function request(array $overrides = []): SelfBillingAuthorizationRequestData
    {
        return SelfBillingAuthorizationRequestData::from(self::input($overrides));
    }

    public static function document(): ElectronicInvoiceData
    {
        $emitter = DocumentFixtures::payload()['emitter'];

        return PreparationFixtures::invoice(
            ['serie' => 'AUTOFATURA', 'ledCode' => 1, 'documentNumber' => 42, 'selfBilling' => DocumentPayloads::selfBilling()],
            [
                'emission' => null,
                'emitter'  => [...$emitter, 'taxId' => ['value' => self::SELLER, 'countryCode' => 'CV'], 'name' => 'Seller'],
                'receiver' => ['taxId' => ['value' => self::BUYER, 'countryCode' => 'CV'], 'name' => 'Buyer'],
            ],
        );
    }

    public static function iud(array $overrides = []): string
    {
        return resolve(BuildIudAction::class)->handle(IudData::from([
            'repositoryCode'   => 3,
            'issueDate'        => '2026-02-08',
            'emitterTaxId'     => self::SELLER,
            'ledCode'          => 7,
            'documentTypeCode' => 'FTE',
            'documentNumber'   => 42,
            'randomCode'       => '1234567890',
            ...$overrides,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        return [
            'authorizationId'                    => self::AUTHORIZATION_ID,
            'authorizationCodeExpirationSeconds' => 3600,
            'iud'                                => self::iud(),
            'serie'                              => 'AUTOFATURA',
            'ledCode'                            => 7,
            'documentNumber'                     => 42,
            ...$overrides,
        ];
    }

    public static function granted(array $overrides = []): string
    {
        return (string) json_encode(['succeeded' => true, 'payload' => self::payload($overrides)]);
    }

    public static function refused(): string
    {
        return (string) json_encode(['succeeded' => false, 'messages' => [
            ['source' => 'BIZ', 'type' => 'ERROR', 'dateTime' => '2026-02-08 09:15:30', 'code' => 'BIZ-901', 'description' => 'Seller is not a micro company.'],
            ['Source' => 'BIZ', 'Type' => 'WARNING', 'Code' => 'BIZ-902', 'Location' => 'mobilePhoneNumber'],
        ]]);
    }
}
