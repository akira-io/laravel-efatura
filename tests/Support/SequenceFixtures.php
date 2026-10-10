<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Sequence\SequenceScope;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

final class SequenceFixtures
{
    public const string STUB = __DIR__ . '/../../database/migrations/create_efatura_sequences_table.php.stub';

    public static function scope(
        string $emitterTaxId = '100200300',
        int $year = 2026,
        int $ledCode = 1,
        DocumentType $documentType = DocumentType::Invoice,
    ): SequenceScope {
        return new SequenceScope($emitterTaxId, $year, $ledCode, $documentType);
    }

    /**
     * @return array<string, array{SequenceScope}>
     */
    public static function otherScopes(): array
    {
        return [
            'another emitter'       => [self::scope(emitterTaxId: '900800700')],
            'another year'          => [self::scope(year: 2027)],
            'another led'           => [self::scope(ledCode: 2)],
            'another document type' => [self::scope(documentType: DocumentType::Receipt)],
        ];
    }

    public static function invoice(array $header = [], array $overrides = []): ElectronicInvoiceData
    {
        return ElectronicInvoiceData::from(F::payload([
            'header'   => DocumentPayloads::header($header),
            'emission' => DocumentPayloads::transmission(),
            ...$overrides,
        ]));
    }

    public static function useConnection(?string $connection, string $table = 'efatura_sequences'): void
    {
        config()->set(['efatura.database.connection' => $connection, 'efatura.database.sequences_table' => $table]);
        app()->forgetInstance(EfaturaConfig::class);
        self::migrate();
    }

    public static function migrate(): void
    {
        $files     = new Filesystem;
        $directory = sys_get_temp_dir() . '/efatura-sequences-' . Str::uuid()->toString();
        $files->ensureDirectoryExists($directory);

        try {
            $files->copy(self::STUB, $directory . '/2026_10_10_000000_create_efatura_sequences_table.php');
            Artisan::call('migrate', ['--path' => $directory, '--realpath' => true]);
        } finally {
            $files->deleteDirectory($directory);
        }
    }
}
