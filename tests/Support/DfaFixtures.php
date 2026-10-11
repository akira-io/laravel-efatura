<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Data\ContingencyData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Issuance\IssuedDocument;
use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Packaging\PreparedDocument;
use Akira\Efatura\Signing\SignedXml;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Transport\EntryResult;
use Akira\Efatura\Transport\SubmissionResult;
use Carbon\CarbonImmutable;

final class DfaFixtures
{
    public static function iud(): string
    {
        return IdentifierFixtures::withCheckDigit('CV326100210020030000001010000000071234567890');
    }

    public static function invoice(array $overrides = []): ElectronicInvoiceData
    {
        return ElectronicInvoiceData::from(F::payload([
            'header' => ['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 1, 'serie' => 'A', 'documentNumber' => 7],
            ...$overrides,
        ]));
    }

    public static function offline(array $overrides = []): ElectronicInvoiceData
    {
        return self::invoice(['emission' => ['issueMode' => 2, 'contingency' => DocumentPayloads::offlineContingency()], ...$overrides]);
    }

    public static function off(?string $iuc = '2026/15', array $overrides = []): ElectronicInvoiceData
    {
        $contingency = DocumentPayloads::offlineContingency(['reasonTypeCode' => '2', 'iuc' => $iuc]);

        return self::invoice(['emission' => ['issueMode' => 3, 'contingency' => array_filter($contingency)], ...$overrides]);
    }

    public static function offWithoutIuc(): ElectronicInvoiceData
    {
        $document    = self::off();
        $contingency = $document->emission?->contingency;
        $emission    = new EmissionContextData(EmissionMode::Off, new ContingencyData($contingency->issueDate, $contingency->reason, $contingency->ledCode));

        return new ElectronicInvoiceData(...[...get_object_vars($document), 'emission' => $emission]);
    }

    public static function prepared(?DocumentData $document = null, ?string $iud = null): PreparedDocument
    {
        $iud ??= self::iud();
        $signed  = new SignedXml('<Dfe/>', 'signature', SignatureProfile::Enveloped, CarbonImmutable::parse('2026-10-02 12:00:00'), 'digest', 'CN=Test', '1');
        $archive = new PackagedArchive('zip', PackageKind::Documents, [$iud . '.xml'], Environment::Test, ['100200300']);

        return new PreparedDocument($document ?? self::invoice(), $iud, '<Dfe/>', $signed, $archive, true);
    }

    public static function issued(?PreparedDocument $prepared = null, bool $authorized = true): IssuedDocument
    {
        $prepared ??= self::prepared();
        $entry = new EntryResult($prepared->iud . '.xml', $prepared->iud, $authorized, $authorized ? 'AUTH-77' : null, null, []);

        return new IssuedDocument($prepared, $entry, new SubmissionResult(PackageKind::Documents, 200, [$entry], CarbonImmutable::parse('2026-10-02 12:00:05')));
    }
}
