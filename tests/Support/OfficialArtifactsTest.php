<?php

declare(strict_types=1);

use Akira\Efatura\Support\OfficialArtifacts;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

beforeEach(function (): void {
    $this->root     = dirname(__DIR__, 2) . '/resources';
    $this->files    = new Filesystem;
    $this->manifest = $this->files->json($this->root . '/official-artifacts.json', JSON_THROW_ON_ERROR);
});

it('resolves bundled artifacts from the Laravel container', function (): void {
    $entry = resolve(OfficialArtifacts::class)->xsdEntry('EnvelopedSignature');

    expect($entry)->toBe(dirname(__DIR__, 2) . '/resources/xsd/efatura/2024-05-27/EnvelopedSignature.xsd');
});

it('records the provenance of every original official download', function (): void {
    expect(array_keys($this->manifest['sources']))->toEqualCanonicalizing(['xml-xsd', 'measurement-units', 'countries-places', 'tax-exemptions']);
});

it('bundles each original official download with its provenance', function (string $source): void {
    $record = $this->manifest['sources'][$source];

    expect($record['url'])->toStartWith('https://efatura.cv/assets/files/')
        ->and($record['publication_label'])->not->toBeEmpty()
        ->and($record['path'])->toStartWith('catalogs/source/')
        ->and($record['sha256'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($this->files->hash($this->root . '/' . $record['path'], 'sha256'))->toBe($record['sha256']);
})->with(['xml-xsd', 'measurement-units', 'countries-places', 'tax-exemptions']);

it('preserves each official file byte for byte against its manifest', function (string $path): void {
    $record = $this->manifest['files'][$path];

    expect($this->files->hash($this->root . '/' . $path, 'sha256'))->toBe($record['sha256'])
        ->and($this->files->size($this->root . '/' . $path))->toBe($record['size'])
        ->and($this->manifest['sources'])->toHaveKey($record['source']);
})->with(fn (): array => array_keys(new Filesystem()->json(dirname(__DIR__, 2) . '/resources/official-artifacts.json', JSON_THROW_ON_ERROR)['files']));

it('lists exactly the bundled official files in its manifest', function (): void {
    $bundled = collect(['catalogs/source', 'xsd/efatura/2024-05-27'])
        ->flatMap(fn (string $directory): array => $this->files->allFiles($this->root . '/' . $directory, hidden: true))
        ->map(fn (SplFileInfo $entry): string => Str::after($entry->getPathname(), $this->root . '/'))
        ->all();

    expect($this->manifest['files'])->not->toBeEmpty()
        ->and($bundled)->toEqualCanonicalizing(array_keys($this->manifest['files']));
});

it('retains every schema archive member byte for byte', function (): void {
    $prefix  = 'xsd/efatura/2024-05-27/';
    $archive = new ZipArchive;
    expect($archive->open($this->root . '/' . $this->manifest['sources']['xml-xsd']['path']))->toBeTrue();

    $entries = collect(range(0, $archive->numFiles - 1))
        ->map(fn (int $index): string => (string) $archive->getNameIndex($index))
        ->reject(fn (string $entry): bool => Str::endsWith($entry, '/'))
        ->values();
    $archived = $entries->mapWithKeys(fn (string $entry): array => [$prefix . $entry => [
        'archive_entry' => $entry, 'source' => 'xml-xsd', 'bytes' => $archive->getFromName($entry),
    ]])->all();
    $archive->close();

    $bundled = collect($archived)->map(fn (array $member, string $path): array => [
        'archive_entry' => $this->manifest['files'][$path]['archive_entry'],
        'source'        => $this->manifest['files'][$path]['source'],
        'bytes'         => $this->files->get($this->root . '/' . $path),
    ])->all();

    expect($archived)->toHaveCount(35)
        ->and($bundled)->toBe($archived)
        ->and(array_keys($this->manifest['files']))->toEqualCanonicalizing([
            ...array_keys($archived), ...array_column($this->manifest['sources'], 'path'),
        ]);
});

it('exposes each official signature entry point documented by the archive', function (string $profile): void {
    $prefix = $this->root . '/xsd/efatura/2024-05-27/';

    expect(resolve(OfficialArtifacts::class)->xsdEntry($profile))->toBe($prefix . $profile . '.xsd')
        ->and($this->files->get($prefix . 'Read Me.txt'))->toContain($profile . '.xsd');
})->with(['EnvelopedSignature', 'InternallyDetachedSignature']);

it('resolves each official example and reference document', function (string $entry): void {
    expect(resolve(OfficialArtifacts::class)->path('xsd/efatura/2024-05-27/' . $entry))->toBeFile();
})->with([
    '1 Invoice - EnvelopedSignature.xml', '1 Invoice - InternallyDetachedSignature.xml',
    '2 InvoiceReceipt.xml', '3 SalesReceipt.xml', '4 Receipt.xml', '5 CreditNote.xml',
    '6 DebitNote.xml', '7 Transport.xml', '8 ReturnNote.xml', '9 RegistrationNote.xml',
    '99 Event.xml', 'Read Me.txt', 'XML Fields Map.txt',
]);

it('resolves every packaged artifact once the export-ignored sources are gone', function (): void {
    $files   = new Filesystem;
    $root    = dirname(__DIR__, 2);
    $dist    = sys_get_temp_dir() . '/efatura-dist-' . Str::random(12);
    $ignored = collect($files->lines($root . '/.gitattributes'))
        ->filter(fn (string $line): bool => Str::startsWith($line, '/resources/') && Str::endsWith($line, ' export-ignore'))
        ->map(fn (string $line): string => Str::between($line, '/resources/', ' export-ignore'))
        ->values();

    $files->copyDirectory($root . '/resources', $dist);
    $ignored->each(fn (string $path): bool => $files->deleteDirectory($dist . '/' . $path));

    try {
        $artifacts = new OfficialArtifacts($files, $dist);
        $shipped   = collect($files->json($dist . '/official-artifacts.json', JSON_THROW_ON_ERROR)['files'])
            ->keys()
            ->reject(fn (string $path): bool => $ignored->contains(fn (string $prefix): bool => Str::startsWith($path, $prefix . '/')));

        expect($ignored->all())->toBe(['catalogs/source'])
            ->and($shipped->map(fn (string $path): string => $artifacts->path($path)))->each->toBeFile()
            ->and($artifacts->xsdEntry('EnvelopedSignature'))->toBeFile()
            ->and($artifacts->xsdEntry('InternallyDetachedSignature'))->toBeFile();
    } finally {
        $files->deleteDirectory($dist);
    }
});
