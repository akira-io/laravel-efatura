<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\ArtifactStore;
use Akira\Efatura\Enums\ArtifactKind;
use Akira\Efatura\Exceptions\StorageException;
use Akira\Efatura\Storage\FilesystemArtifactStore;
use Akira\Efatura\Storage\StoredArtifact;
use Akira\Efatura\Tests\Support\StorageFixtures as S;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;

beforeEach(function (): void {
    Storage::fake('archive');
});

it('stores each kind under the repository, the emitter, the month and the IUD', function (ArtifactKind $kind, string $file): void {
    $iud    = S::iud();
    $stored = S::store()->put($iud, $kind, 'bytes of ' . $kind->value);

    expect(resolve(ArtifactStore::class))->toBeInstanceOf(FilesystemArtifactStore::class)
        ->and($stored)->toEqual(new StoredArtifact(
            $iud,
            $kind,
            'archive',
            'efatura/3/100200300/2026/10/' . $iud . '/' . str_replace('{iud}', $iud, $file),
            mb_strlen('bytes of ' . $kind->value, '8bit'),
            hash('sha256', 'bytes of ' . $kind->value),
        ))
        ->and(Storage::disk('archive')->get($stored->path))->toBe('bytes of ' . $kind->value);
})->with([
    'signed xml' => [ArtifactKind::SignedXml, '{iud}.xml'],
    'archive'    => [ArtifactKind::Archive, '{iud}.zip'],
    'response'   => [ArtifactKind::Response, 'response.json'],
    'dfa'        => [ArtifactKind::Dfa, '{iud}.pdf'],
]);

it('records the size of the contents in bytes', function (string $contents, int $bytes): void {
    $stored = S::store()->put(S::iud(), ArtifactKind::Dfa, $contents);

    expect($stored->size)->toBe($bytes)
        ->and(Storage::disk('archive')->size($stored->path))->toBe($bytes)
        ->and(S::store()->get($stored))->toBe($contents);
})->with([
    'multibyte text' => ['Preço € «ok»', 17],
    'binary'         => ["%PDF-\x00\xff\xfe\n", 9],
]);

it('takes the year and the month from the date inside the IUD', function (string $date, string $folder): void {
    $iud = S::iud(date: $date);

    expect(S::store()->put($iud, ArtifactKind::SignedXml, '<Dfe/>')->path)
        ->toBe('efatura/3/100200300/' . $folder . '/' . $iud . '/' . $iud . '.xml');
})->with([
    'first day of the year' => ['260101', '2026/01'],
    'last day of the year'  => ['251231', '2025/12'],
    'end of february'       => ['280229', '2028/02'],
]);

it('keeps emitters and repositories apart', function (): void {
    $store = S::store();
    $paths = collect([S::iud(), S::iud(emitter: '200300400'), S::iud(repository: 1), S::iud(repository: 2, emitter: '200300400')])
        ->map(fn (string $iud): StoredArtifact => $store->put($iud, ArtifactKind::SignedXml, $iud))
        ->map(fn (StoredArtifact $stored): string => Str::before($stored->path, '/CV'))
        ->all();

    expect($paths)->toBe([
        'efatura/3/100200300/2026/10',
        'efatura/3/200300400/2026/10',
        'efatura/1/100200300/2026/10',
        'efatura/2/200300400/2026/10',
    ]);
});

it('writes to the default disk of the application when no disk is configured', function (): void {
    Storage::fake('vault');
    config()->set('filesystems.default', 'vault');

    $stored = S::store(null, 'fiscal/artifacts')->put(S::iud(), ArtifactKind::Dfa, '%PDF-');

    expect($stored->disk)->toBe('vault')
        ->and($stored->path)->toStartWith('fiscal/artifacts/3/')
        ->and(Storage::disk('vault')->get($stored->path))->toBe('%PDF-')
        ->and(Storage::disk('archive')->allFiles())->toBe([]);
});

it('returns the same artifact when the same bytes are stored again', function (): void {
    $store = S::store();
    $first = $store->put(S::iud(), ArtifactKind::Archive, 'zip');

    expect($store->put(S::iud(), ArtifactKind::Archive, 'zip'))->toEqual($first)
        ->and(Storage::disk('archive')->allFiles())->toBe([$first->path]);
});

it('never overwrites an artifact with different bytes', function (): void {
    $store  = S::store();
    $stored = $store->put(S::iud(), ArtifactKind::Archive, 'zip');

    expect(fn (): StoredArtifact => $store->put(S::iud(), ArtifactKind::Archive, 'another zip'))
        ->toThrow(function (StorageException $exception) use ($stored): void {
            expect($exception->errorCode)->toBe('storage.conflict')
                ->and($exception->getMessage())->toBe('storage.conflict')
                ->and($exception->context)->toBe(['path' => $stored->path])
                ->and($exception->retryable)->toBeFalse();
        })
        ->and(Storage::disk('archive')->get($stored->path))->toBe('zip');
});

it('reads back the bytes it stored', function (): void {
    $store = S::store();

    expect($store->get($store->put(S::iud(), ArtifactKind::Response, '{"succeeded":true}')))->toBe('{"succeeded":true}');
});

it('reports an artifact that is not on the disk as missing', function (Closure $artifact): void {
    $store  = S::store();
    $stored = $artifact($store);

    expect(fn (): string => $store->get($stored))->toThrow(function (StorageException $exception) use ($stored): void {
        expect($exception->errorCode)->toBe('storage.missing')
            ->and($exception->context)->toBe(['path' => $stored->path])
            ->and($exception->retryable)->toBeFalse();
    });
})->with([
    'never stored' => [fn (): StoredArtifact => new StoredArtifact(S::iud(), ArtifactKind::Dfa, 'archive', 'efatura/3/100200300/2026/10/' . S::iud() . '/' . S::iud() . '.pdf', 5, hash('sha256', '%PDF-'))],
    'deleted'      => [function (ArtifactStore $store): StoredArtifact {
        $stored = $store->put(S::iud(), ArtifactKind::Dfa, '%PDF-');
        Storage::disk('archive')->delete($stored->path);

        return $stored;
    }],
    'another path' => [fn (ArtifactStore $store): StoredArtifact => new StoredArtifact(...[...get_object_vars($store->put(S::iud(), ArtifactKind::Dfa, '%PDF-')), 'path' => 'certificates/key.pem'])],
    'another disk' => [fn (ArtifactStore $store): StoredArtifact => new StoredArtifact(...[...get_object_vars($store->put(S::iud(), ArtifactKind::Dfa, '%PDF-')), 'disk' => 'local'])],
]);

it('refuses bytes whose checksum no longer matches', function (): void {
    $store  = S::store();
    $stored = $store->put(S::iud(), ArtifactKind::SignedXml, '<Dfe/>');
    Storage::disk('archive')->put($stored->path, '<Dfe>tampered</Dfe>');

    expect(fn (): string => $store->get($stored))->toThrow(function (StorageException $exception) use ($stored): void {
        expect($exception->errorCode)->toBe('storage.corrupted')
            ->and($exception->context)->toBe(['path' => $stored->path]);
    });
});

it('rejects an IUD that cannot name a safe path', function (string $iud): void {
    expect(fn (): StoredArtifact => S::store()->put($iud, ArtifactKind::SignedXml, '<Dfe/>'))
        ->toThrow(function (StorageException $exception): void {
            expect($exception->errorCode)->toBe('storage.invalid_iud')
                ->and($exception->field)->toBe('iud')
                ->and($exception->context)->toBe([]);
        })
        ->and(Storage::disk('archive')->allFiles())->toBe([]);
})->with([
    'traversal'    => ['../../../etc/passwd'],
    'wrong digit'  => [mb_substr(S::iud(), 0, -1) . ((int) mb_substr(S::iud(), -1) + 1) % 10],
    'unknown repo' => [S::iud(repository: 4)],
    'separator'    => ['CV3261002100200300/00001010000000071234567890'],
    'empty'        => [''],
]);

it('reports a disk that is not configured', function (): void {
    expect(fn (): StoredArtifact => S::store('missing-disk')->put(S::iud(), ArtifactKind::SignedXml, '<Dfe/>'))
        ->toThrow(function (StorageException $exception): void {
            expect($exception->errorCode)->toBe('storage.disk_invalid')
                ->and($exception->field)->toBe('efatura.storage.disk')
                ->and($exception->context)->toBe(['disk' => 'missing-disk'])
                ->and($exception->retryable)->toBeFalse();
        });
});

it('reports a failing disk as retryable', function (?bool $present, bool $throws, ?string $previous, Closure $operation): void {
    S::failingDisk($present, $throws);
    $store = S::store();

    expect(fn (): mixed => $operation($store))->toThrow(function (StorageException $exception) use ($previous): void {
        expect($exception->errorCode)->toBe('storage.unavailable')
            ->and($exception->context)->toBe(['path' => 'efatura/3/100200300/2026/10/' . S::iud() . '/' . S::iud() . '.zip'])
            ->and($exception->retryable)->toBeTrue()
            ->and($exception->getPrevious() instanceof Throwable ? $exception->getPrevious()::class : null)->toBe($previous);
    });
})->with([
    'existence check fails'     => [null, false, UnableToCheckFileExistence::class, fn (ArtifactStore $store): StoredArtifact => $store->put(S::iud(), ArtifactKind::Archive, 'zip')],
    'write fails quietly'       => [false, false, null, fn (ArtifactStore $store): StoredArtifact => $store->put(S::iud(), ArtifactKind::Archive, 'zip')],
    'write throws'              => [false, true, UnableToWriteFile::class, fn (ArtifactStore $store): StoredArtifact => $store->put(S::iud(), ArtifactKind::Archive, 'zip')],
    'read of existing fails'    => [true, false, null, fn (ArtifactStore $store): StoredArtifact => $store->put(S::iud(), ArtifactKind::Archive, 'zip')],
    'read of existing throws'   => [true, true, UnableToReadFile::class, fn (ArtifactStore $store): StoredArtifact => $store->put(S::iud(), ArtifactKind::Archive, 'zip')],
    'get existence check fails' => [null, true, UnableToCheckFileExistence::class, fn (ArtifactStore $store): string => $store->get(storedZip())],
    'get read fails'            => [true, false, null, fn (ArtifactStore $store): string => $store->get(storedZip())],
]);

function storedZip(): StoredArtifact
{
    return new StoredArtifact(S::iud(), ArtifactKind::Archive, 'archive', 'efatura/3/100200300/2026/10/' . S::iud() . '/' . S::iud() . '.zip', 3, hash('sha256', 'zip'));
}

it('refuses to write to a disk whose files are public', function (): void {
    Storage::fake('public-archive', ['visibility' => 'public']);

    expect(fn (): StoredArtifact => S::store('public-archive')->put(S::iud(), ArtifactKind::SignedXml, '<Dfe/>'))
        ->toThrow(function (StorageException $exception): void {
            expect($exception->errorCode)->toBe('storage.public_disk')
                ->and($exception->field)->toBe('efatura.storage.disk')
                ->and($exception->context)->toBe(['disk' => 'public-archive'])
                ->and($exception->retryable)->toBeFalse();
        })
        ->and(Storage::disk('public-archive')->allFiles())->toBe([]);
});

it('writes to a disk whose files are private', function (): void {
    Storage::fake('private-archive', ['visibility' => 'private']);

    expect(S::store('private-archive')->put(S::iud(), ArtifactKind::SignedXml, '<Dfe/>')->disk)->toBe('private-archive');
});
