<?php

declare(strict_types=1);

use Akira\Efatura\Actions\StoreIssuedArtifactsAction;
use Akira\Efatura\Contracts\ArtifactStore;
use Akira\Efatura\Dfa\RenderedDfa;
use Akira\Efatura\Enums\ArtifactKind;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Exceptions\StorageException;
use Akira\Efatura\Issuance\IssuedDocument;
use Akira\Efatura\Storage\StoredArtifact;
use Akira\Efatura\Tests\Support\DfaFixtures as D;
use Akira\Efatura\Tests\Support\StorageFixtures as S;
use Akira\Efatura\Transport\EntryResult;
use Akira\Efatura\Transport\RemoteMessage;
use Akira\Efatura\Transport\SubmissionResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('archive');
    S::store();
});

function rejectedIssue(): IssuedDocument
{
    $prepared = D::prepared();
    $entry    = new EntryResult($prepared->iud . '.xml', $prepared->iud, false, null, '2026-10-02T12:00:04', [
        new RemoteMessage('platform', 'Error', '2026-10-02T12:00:04', 'E-17', 'Preço «inválido» / linha 2', '/Dfe/Lines/Line[2]'),
        new RemoteMessage('xsd', 'Warning', null, 'W-1', null, null),
    ]);

    return new IssuedDocument($prepared, $entry, new SubmissionResult(PackageKind::Documents, 422, [$entry], CarbonImmutable::parse('2026-10-02 12:00:05', 'Atlantic/Cape_Verde')));
}

it('stores the signed XML, the archive and the response evidence of an issued document', function (): void {
    $issued    = D::issued();
    $artifacts = resolve(StoreIssuedArtifactsAction::class)->handle($issued);
    $folder    = 'efatura/3/100200300/2026/10/' . D::iud() . '/';

    expect(collect($artifacts)->map(fn (StoredArtifact $stored): array => [$stored->kind, $stored->path])->all())->toBe([
        [ArtifactKind::SignedXml, $folder . D::iud() . '.xml'],
        [ArtifactKind::Archive, $folder . D::iud() . '.zip'],
        [ArtifactKind::Response, $folder . 'response.json'],
    ])
        ->and(Storage::disk('archive')->get($folder . D::iud() . '.xml'))->toBe($issued->prepared->signed->xml)
        ->and(Storage::disk('archive')->get($folder . D::iud() . '.zip'))->toBe($issued->prepared->archive->bytes)
        ->and(json_decode((string) Storage::disk('archive')->get($folder . 'response.json'), true))->toMatchArray([
            'identifier'        => D::iud(),
            'succeeded'         => true,
            'authorizationCode' => 'AUTH-77',
        ]);
});

it('adds the DFA when one is given', function (): void {
    $artifacts = resolve(StoreIssuedArtifactsAction::class)->handle(D::issued(), new RenderedDfa('%PDF-1.7 dfa', D::iud() . '.pdf'));

    expect($artifacts)->toHaveCount(4)
        ->and($artifacts[3]->kind)->toBe(ArtifactKind::Dfa)
        ->and(resolve(ArtifactStore::class)->get($artifacts[3]))->toBe('%PDF-1.7 dfa');
});

it('writes the response evidence as stable JSON', function (): void {
    $artifacts = resolve(StoreIssuedArtifactsAction::class)->handle(rejectedIssue());

    expect(resolve(ArtifactStore::class)->get($artifacts[2]))->toBe(<<<'JSON'
        {
            "entryName": "CV3261002100200300000010100000000712345678909.xml",
            "identifier": "CV3261002100200300000010100000000712345678909",
            "succeeded": false,
            "authorizationCode": null,
            "signatureTimeStamp": "2026-10-02T12:00:04",
            "messages": [
                {
                    "source": "platform",
                    "type": "Error",
                    "dateTime": "2026-10-02T12:00:04",
                    "code": "E-17",
                    "description": "Preço «inválido» / linha 2",
                    "location": "/Dfe/Lines/Line[2]"
                },
                {
                    "source": "xsd",
                    "type": "Warning",
                    "dateTime": null,
                    "code": "W-1",
                    "description": null,
                    "location": null
                }
            ],
            "packageKind": "dfe",
            "httpStatus": 422,
            "receivedAt": "2026-10-02T12:00:05-01:00"
        }
        JSON);
});

it('stores the same issued document twice without a conflict', function (): void {
    $first = resolve(StoreIssuedArtifactsAction::class)->handle(rejectedIssue());

    expect(resolve(StoreIssuedArtifactsAction::class)->handle(rejectedIssue()))->toEqual($first)
        ->and(Storage::disk('archive')->allFiles())->toHaveCount(3);
});

it('refuses a second answer for an IUD whose evidence is already stored', function (): void {
    resolve(StoreIssuedArtifactsAction::class)->handle(rejectedIssue());

    expect(fn (): array => resolve(StoreIssuedArtifactsAction::class)->handle(D::issued()))
        ->toThrow(function (StorageException $exception): void {
            expect($exception->errorCode)->toBe('storage.conflict')
                ->and($exception->context)->toBe(['path' => 'efatura/3/100200300/2026/10/' . D::iud() . '/response.json']);
        });
});

it('never writes a configured secret into a stored artifact or its path', function (): void {
    $secrets = [
        'efatura.transmitter.middleware_key'      => 'middleware-key-6f1c2e',
        'efatura.transmitter.oauth.client_secret' => 'client-secret-9a7d41',
        'efatura.certificates.passphrase'         => 'passphrase-3b8e5f',
    ];
    config()->set($secrets);

    $artifacts = resolve(StoreIssuedArtifactsAction::class)->handle(rejectedIssue(), new RenderedDfa('%PDF-1.7 dfa', D::iud() . '.pdf'));
    $written   = collect(Storage::disk('archive')->allFiles())
        ->flatMap(fn (string $path): array => [$path, (string) Storage::disk('archive')->get($path)])
        ->merge(collect($artifacts)->map(fn (StoredArtifact $stored): string => json_encode(get_object_vars($stored), JSON_THROW_ON_ERROR)))
        ->implode("\n");

    expect(config('efatura.transmitter.oauth.client_secret'))->toBe('client-secret-9a7d41')
        ->and(Storage::disk('archive')->allFiles())->toHaveCount(4)
        ->and($written)->not->toContain(...array_values($secrets));
});
