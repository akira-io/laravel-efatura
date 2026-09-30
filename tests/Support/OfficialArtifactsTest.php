<?php

declare(strict_types=1);

use Akira\Efatura\Support\OfficialArtifacts;

it('bundles the original official downloads with provenance', function (): void {
    $root = dirname(__DIR__, 2) . '/resources';
    expect($root . '/official-artifacts.json')->toBeFile();
    $manifest = json_decode(file_get_contents($root . '/official-artifacts.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['sources'])->toHaveKeys(['xml-xsd', 'measurement-units', 'countries-places', 'tax-exemptions']);

    foreach ($manifest['sources'] as $source) {
        expect($source['url'])->toStartWith('https://efatura.cv/assets/files/')
            ->and($source['publication_label'])->not->toBeEmpty()
            ->and($source['path'])->toStartWith('catalogs/source/')
            ->and($source['sha256'])->toMatch('/^[a-f0-9]{64}$/')
            ->and(hash_file('sha256', $root . '/' . $source['path']))->toBe($source['sha256']);
    }
});

it('preserves every official file byte for byte against its manifest', function (): void {
    $root = dirname(__DIR__, 2) . '/resources';
    expect($root . '/official-artifacts.json')->toBeFile();
    $manifest = json_decode(file_get_contents($root . '/official-artifacts.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['files'])->not->toBeEmpty();

    foreach ($manifest['files'] as $path => $file) {
        expect(hash_file('sha256', $root . '/' . $path))->toBe($file['sha256'])
            ->and(filesize($root . '/' . $path))->toBe($file['size'])
            ->and($manifest['sources'])->toHaveKey($file['source']);
    }

    $bundled = [];
    foreach (['catalogs/source', 'xsd/efatura/2024-05-27'] as $directory) {
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
        foreach ($entries as $entry) {
            $bundled[] = substr($entry->getPathname(), strlen($root) + 1);
        }
    }

    expect($bundled)->toEqualCanonicalizing(array_keys($manifest['files']));
});

it('retains every archive member and exposes both official signature entry points', function (): void {
    $root = dirname(__DIR__, 2) . '/resources';
    expect($root . '/official-artifacts.json')->toBeFile();
    $manifest = json_decode(file_get_contents($root . '/official-artifacts.json'), true, flags: JSON_THROW_ON_ERROR);
    $archive  = new ZipArchive;
    expect($archive->open($root . '/' . $manifest['sources']['xml-xsd']['path']))->toBeTrue();
    $prefix  = 'xsd/efatura/2024-05-27/';
    $members = [];

    for ($index = 0; $index < $archive->numFiles; $index++) {
        $entry = $archive->getNameIndex($index);
        if (str_ends_with($entry, '/')) {
            continue;
        }

        $members[] = $prefix . $entry;
        expect($manifest['files'][$prefix . $entry]['archive_entry'])->toBe($entry)
            ->and($manifest['files'][$prefix . $entry]['source'])->toBe('xml-xsd')
            ->and(file_get_contents($root . '/' . $prefix . $entry))->toBe($archive->getFromIndex($index));
    }

    $archive->close();
    expect($members)->toHaveCount(35)
        ->and(array_keys($manifest['files']))->toEqualCanonicalizing([
            ...$members, ...array_column($manifest['sources'], 'path'),
        ]);

    $artifacts = new OfficialArtifacts;
    foreach (['EnvelopedSignature', 'InternallyDetachedSignature'] as $profile) {
        expect($artifacts->xsdEntry($profile))->toBe($root . '/' . $prefix . $profile . '.xsd')
            ->and(file_get_contents($root . '/' . $prefix . 'Read Me.txt'))->toContain($profile . '.xsd');
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
