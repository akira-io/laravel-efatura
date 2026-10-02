<?php

declare(strict_types=1);

use Akira\Efatura\Support\OfficialArtifacts;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

it('resolves bundled artifacts from the Laravel container', function (): void {
    $entry = resolve(OfficialArtifacts::class)->xsdEntry('EnvelopedSignature');

    expect($entry)->toBe(dirname(__DIR__, 2) . '/resources/xsd/efatura/2024-05-27/EnvelopedSignature.xsd');
});

it('bundles the original official downloads with provenance', function (): void {
    $root  = dirname(__DIR__, 2) . '/resources';
    $files = new Filesystem;
    expect($root . '/official-artifacts.json')->toBeFile();
    $manifest = $files->json($root . '/official-artifacts.json', JSON_THROW_ON_ERROR);

    expect($manifest['sources'])->toHaveKeys(['xml-xsd', 'measurement-units', 'countries-places', 'tax-exemptions']);

    foreach ($manifest['sources'] as $source) {
        expect($source['url'])->toStartWith('https://efatura.cv/assets/files/')
            ->and($source['publication_label'])->not->toBeEmpty()
            ->and($source['path'])->toStartWith('catalogs/source/')
            ->and($source['sha256'])->toMatch('/^[a-f0-9]{64}$/')
            ->and($files->hash($root . '/' . $source['path'], 'sha256'))->toBe($source['sha256']);
    }
});

it('preserves every official file byte for byte against its manifest', function (): void {
    $root  = dirname(__DIR__, 2) . '/resources';
    $files = new Filesystem;
    expect($root . '/official-artifacts.json')->toBeFile();
    $manifest = $files->json($root . '/official-artifacts.json', JSON_THROW_ON_ERROR);

    expect($manifest['files'])->not->toBeEmpty();

    foreach ($manifest['files'] as $path => $file) {
        expect($files->hash($root . '/' . $path, 'sha256'))->toBe($file['sha256'])
            ->and($files->size($root . '/' . $path))->toBe($file['size'])
            ->and($manifest['sources'])->toHaveKey($file['source']);
    }

    $bundled = [];
    foreach (['catalogs/source', 'xsd/efatura/2024-05-27'] as $directory) {
        foreach ($files->allFiles($root . '/' . $directory, hidden: true) as $entry) {
            $bundled[] = Str::after($entry->getPathname(), $root . '/');
        }
    }

    expect($bundled)->toEqualCanonicalizing(collect($manifest['files'])->keys()->all());
});

it('retains every archive member and exposes both official signature entry points', function (): void {
    $root  = dirname(__DIR__, 2) . '/resources';
    $files = new Filesystem;
    expect($root . '/official-artifacts.json')->toBeFile();
    $manifest = $files->json($root . '/official-artifacts.json', JSON_THROW_ON_ERROR);
    $archive  = new ZipArchive;
    expect($archive->open($root . '/' . $manifest['sources']['xml-xsd']['path']))->toBeTrue();
    $prefix  = 'xsd/efatura/2024-05-27/';
    $members = [];

    for ($index = 0; $index < $archive->numFiles; $index++) {
        $entry = $archive->getNameIndex($index);
        if (Str::endsWith($entry, '/')) {
            continue;
        }

        $members[] = $prefix . $entry;
        expect($manifest['files'][$prefix . $entry]['archive_entry'])->toBe($entry)
            ->and($manifest['files'][$prefix . $entry]['source'])->toBe('xml-xsd')
            ->and($files->get($root . '/' . $prefix . $entry))->toBe($archive->getFromIndex($index));
    }

    $archive->close();
    expect($members)->toHaveCount(35)
        ->and(collect($manifest['files'])->keys()->all())->toEqualCanonicalizing([
            ...$members, ...array_column($manifest['sources'], 'path'),
        ]);

    $artifacts = resolve(OfficialArtifacts::class);
    foreach (['EnvelopedSignature', 'InternallyDetachedSignature'] as $profile) {
        expect($artifacts->xsdEntry($profile))->toBe($root . '/' . $prefix . $profile . '.xsd')
            ->and($files->get($root . '/' . $prefix . 'Read Me.txt'))->toContain($profile . '.xsd');
    }

    foreach ([
        '1 Invoice - EnvelopedSignature.xml', '1 Invoice - InternallyDetachedSignature.xml',
        '2 InvoiceReceipt.xml', '3 SalesReceipt.xml', '4 Receipt.xml', '5 CreditNote.xml',
        '6 DebitNote.xml', '7 Transport.xml', '8 ReturnNote.xml', '9 RegistrationNote.xml',
        '99 Event.xml', 'Read Me.txt', 'XML Fields Map.txt',
    ] as $entry) {
        expect($artifacts->path($prefix . $entry))->toBeFile();
    }
});

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
