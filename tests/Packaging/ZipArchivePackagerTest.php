<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\Packager;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\PackagingException;
use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Packaging\ZipArchivePackager;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\PackageFixtures as P;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir() . '/efatura-packaging-' . Str::uuid()->toString();
    new Filesystem()->ensureDirectoryExists($this->directory);
});

afterEach(function (): void {
    new Filesystem()->deleteDirectory($this->directory);
});

it('is the packager the container resolves', function (): void {
    expect(resolve(Packager::class))->toBeInstanceOf(ZipArchivePackager::class);
});

it('names each dfe entry after its iud in the order given and deflates it', function (array $numbers, SignatureProfile $profile): void {
    $xml      = array_map(fn (int $number): string => P::document($number, $profile), $numbers);
    $archive  = P::packager($this->directory)->package($xml);
    $entries  = P::entries($archive->bytes, $this->directory);
    $expected = array_map(fn (string $signed): string => P::id($signed) . '.xml', $xml);

    expect($archive->kind)->toBe(PackageKind::Documents)
        ->and($archive->entries)->toBe($expected)
        ->and(array_column($entries, 'name'))->toBe($expected)
        ->and(array_column($entries, 'contents'))->toBe($xml)
        ->and(array_column($entries, 'method'))->each->toBe(ZipArchive::CM_DEFLATE)
        ->and(array_column($entries, 'crc'))->toBe(array_map(crc32(...), $xml))
        ->and(array_map(strlen(...), $expected))->each->toBe(49)
        ->and(scandir($this->directory))->toBe(['.', '..']);
})->with([
    'one document'    => [[1]],
    'three documents' => [[3, 1, 2]],
])->with(SignatureProfile::cases());

it('names each event entry after its event id', function (SignatureProfile $profile): void {
    $xml     = [P::event('2026-10-02T12:00:00', $profile), P::event('2026-10-02T12:00:01', $profile)];
    $archive = P::packager($this->directory)->package($xml);
    $entries = P::entries($archive->bytes, $this->directory);

    expect($archive->kind)->toBe(PackageKind::Events)
        ->and($archive->entries)->toBe([P::id($xml[0]) . '.xml', P::id($xml[1]) . '.xml'])
        ->and(array_column($entries, 'name'))->toBe($archive->entries)
        ->and(array_column($entries, 'contents'))->toBe($xml)
        ->and(array_map(strlen(...), $archive->entries))->each->toBe(28);
})->with(SignatureProfile::cases());

it('writes the same bytes for the same entries', function (): void {
    $xml = [P::document(1), P::document(2)];

    expect(P::packager()->package($xml)->bytes)->toBe(P::packager()->package($xml)->bytes);
});

it('refuses entries it cannot package without echoing them', function (Closure $xml, string $errorCode, ?array $context): void {
    $entries = $xml();

    expect(fn (): PackagedArchive => P::packager($this->directory)->package($entries))
        ->toThrow(function (EfaturaException $exception) use ($errorCode, $context, $entries): void {
            expect($exception->errorCode)->toBe($errorCode)
                ->and($exception->getMessage())->toBe($errorCode)
                ->and($exception->context)->toBe($context ?? $exception->context)
                ->and(collect($entries)->filter(fn (string $entry): bool => $entry !== '' && str_contains($exception->getMessage(), $entry)))->toBeEmpty();
        });

    expect(scandir($this->directory))->toBe(['.', '..']);
})->with([
    'no entries'          => [fn (): array => [], 'package.empty', []],
    'a dfe and an event'  => [fn (): array => [P::document(), P::event()], 'package.mixed_kinds', ['entry' => 1]],
    'an unsigned dfe'     => [fn (): array => [S::unsigned()], 'package.unsigned', ['entry' => 0]],
    'an unsigned event'   => [fn (): array => [P::document(), S::unsigned('FDC')], 'package.unsigned', ['entry' => 1]],
    'a foreign root'      => [fn (): array => ['<Foo xmlns="urn:cv:efatura:xsd:v1.0" Id="x"/>'], 'package.unsupported_root', ['entry' => 0]],
    'an unqualified dfe'  => [fn (): array => ['<Dfe Id="x"/>'], 'package.unsupported_root', ['entry' => 0]],
    'an empty detached'   => [fn (): array => ['<internally-detached/>'], 'package.unsupported_root', ['entry' => 0]],
    'a wrong check digit' => [
        fn (): array => [P::withWrongCheckDigit(P::document())],
        'package.invalid_identifier',
        ['entry' => 0],
    ],
    'a parent path' => [
        fn (): array => [P::withId(P::document(), fn (string $id): string => '../' . $id)],
        'package.invalid_identifier',
        ['entry' => 0],
    ],
    'a nested path' => [
        fn (): array => [P::withId(P::event(), fn (string $id): string => 'a/' . substr($id, 2))],
        'package.invalid_identifier',
        ['entry' => 0],
    ],
    'an event without date' => [
        fn (): array => [P::withId(P::event(), fn (string $id): string => substr($id, 0, 3) . '000000000000' . substr($id, 15))],
        'package.invalid_identifier',
        ['entry' => 0],
    ],
    'the same dfe twice' => [fn (): array => [P::document(), P::document(2), P::document()], 'package.duplicate_entry', ['entry' => 2]],
    'too many entries'   => [fn (): array => array_fill(0, Fiscal::MAX_PACKAGE_ENTRIES + 1, ''), 'package.too_many_entries',
        ['limit' => Fiscal::MAX_PACKAGE_ENTRIES, 'entries' => Fiscal::MAX_PACKAGE_ENTRIES + 1]],
    'too many bytes' => [fn (): array => [str_repeat('a', Fiscal::MAX_PACKAGE_BYTES - 1), 'ab'], 'package.too_large',
        ['limit' => Fiscal::MAX_PACKAGE_BYTES, 'bytes' => Fiscal::MAX_PACKAGE_BYTES + 1]],
    'a document type'           => [fn (): array => ['<!DOCTYPE Dfe [<!ENTITY a "b">]><Dfe>&a;</Dfe>'], 'xml.doctype_forbidden', ['violations' => 0]],
    'a nul character'           => [fn (): array => [P::withId(P::document(), fn (string $id): string => $id . "\0")], 'xml.malformed', null],
    'a qualified detached root' => [
        static function (): array {
            $detached = P::document(1, SignatureProfile::InternallyDetached);

            return [Str::replaceFirst('<internally-detached>', '<internally-detached xmlns="urn:example">', $detached)];
        },
        'package.unsupported_root',
        ['entry' => 0],
    ],
    'a signature in another namespace' => [
        fn (): array => [str_replace('xmlns:ds="' . Fiscal::XMLDSIG_NAMESPACE . '"', 'xmlns:ds="urn:example"', P::document())],
        'package.unsigned',
        ['entry' => 0],
    ],
    'a nested signature' => [
        fn (): array => [str_replace('</Dfe>', '<Wrapper xmlns="urn:example">' . P::EMPTY_SIGNATURE . '</Wrapper></Dfe>', S::unsigned())],
        'package.unsigned',
        ['entry' => 0],
    ],
]);

it('accepts entries that add up to exactly the byte limit', function (): void {
    $xml     = P::document();
    $padded  = P::padded($xml, Fiscal::MAX_PACKAGE_BYTES);
    $archive = P::packager($this->directory)->package([$padded]);

    expect(Fiscal::MAX_PACKAGE_BYTES)->toBe(10_485_760)
        ->and(Fiscal::MAX_PACKAGE_ENTRIES)->toBe(1000)
        ->and(strlen($padded))->toBe(Fiscal::MAX_PACKAGE_BYTES)
        ->and(P::entries($archive->bytes, $this->directory)[0]['contents'])->toBe($padded)
        ->and(strlen($archive->bytes))->toBeLessThan(Fiscal::MAX_PACKAGE_BYTES);
});

it('accepts exactly as many entries as the limit', function (): void {
    $xml     = P::document();
    $entries = array_map(fn (int $number): string => P::withId($xml, fn (string $iud): string => P::renumbered($iud, $number)), range(1, 1000));

    expect(P::packager($this->directory)->package($entries)->entries)->toHaveCount(Fiscal::MAX_PACKAGE_ENTRIES)
        ->and(scandir($this->directory))->toBe(['.', '..']);
});

it('stamps every entry with the same instant in any process timezone', function (string $timezone): void {
    $original = getenv('TZ');
    putenv('TZ=' . $timezone);

    try {
        $entries = P::entries(P::packager($this->directory)->package([P::document(1), P::document(2)])->bytes, $this->directory);
    } finally {
        putenv($original === false ? 'TZ' : 'TZ=' . $original);
    }

    expect(array_column($entries, 'mtime'))->each->toBe(CarbonImmutable::parse('1980-01-02T12:00:00Z')->getTimestamp());
})->with(['UTC', 'Atlantic/Cape_Verde', 'Pacific/Honolulu', 'Pacific/Kiritimati']);

it('reports an archive it cannot write without leaving a file behind', function (): void {
    $packager = P::packager($this->directory . '/missing');

    expect(fn (): PackagedArchive => $packager->package([P::document()]))->toThrow(function (PackagingException $exception): void {
        expect($exception->errorCode)->toBe('package.write_failed')
            ->and($exception->context)->toBe([]);
    })->and(scandir($this->directory))->toBe(['.', '..']);
});
