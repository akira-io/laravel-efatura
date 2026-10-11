# Artifact storage

The package can keep the artifacts of an issued document on a Laravel
filesystem disk: the signed XML, the ZIP archive that was sent, the evidence of
the platform answer and the DFA PDF. Storage is opt-in. Nothing is written
unless the application calls `StoreIssuedArtifactsAction`; no listener or job
of the package stores anything on its own.

```php
$issued    = resolve(IssueDocumentAction::class)->handle($document);
$dfa       = resolve(DfaRenderer::class)->render(resolve(BuildDfaDataAction::class)->handle($issued));
$artifacts = resolve(StoreIssuedArtifactsAction::class)->handle($issued, $dfa);

foreach ($artifacts as $artifact) {
    $artifact->kind;      // ArtifactKind
    $artifact->disk;      // the disk name
    $artifact->path;      // the path on that disk
    $artifact->size;      // bytes
    $artifact->checksum;  // sha256, hex
}
```

`handle(IssuedDocument $issued, ?RenderedDfa $dfa = null)` returns a
`list<StoredArtifact>` in the order signed XML, archive, response and, when a
DFA is given, DFA. The DFA must be the ONLINE model rendered for the same IUD
(`RenderedDfa::$iud` and `RenderedDfa::$model`); any other fails with
`storage.dfa_mismatch` before anything is written. Keep the `StoredArtifact` values (for example as columns of
the application's own table): `ArtifactStore::get()` takes one and returns the
bytes after checking the checksum.

## Disk and path

| Key | Default | Meaning |
| --- | --- | --- |
| `efatura.storage.disk` (`EFATURA_STORAGE_DISK`) | null | A disk of `config/filesystems.php`; null uses `filesystems.default` |
| `efatura.storage.path` (`EFATURA_STORAGE_PATH`) | `efatura` | A relative directory on that disk, without `..`, `.`, empty segments or a leading slash |

Every artifact lives under

```text
{storage.path}/{repository}/{emitter NIF}/{YYYY}/{MM}/{IUD}/{file}
```

The repository code, the emitter NIF and the year and month all come from the
IUD itself, never from the clock, so the same document always lands in the same
folder. The IUD is validated first (format, repository 1 to 3, NIF digits and
check digit); one that fails is refused with `storage.invalid_iud` and nothing
is written, so no part of a path can climb out of `storage.path`. Two emitters
or two repositories never share a folder.

## Kinds

| `ArtifactKind` | Value | File | Contents |
| --- | --- | --- | --- |
| `SignedXml` | `signed_xml` | `{IUD}.xml` | `$issued->prepared->signed->xml` |
| `Archive` | `archive` | `{IUD}.zip` | `$issued->prepared->archive->bytes`, the ZIP that was sent |
| `Response` | `response` | `response.json` | The response evidence, below |
| `Dfa` | `dfa` | `{IUD}.pdf` | `RenderedDfa::$bytes` |

The response evidence is the `EntryResult` of the document and the status of
the submission, because the raw response body is not kept. Its keys always come
in this order:

```json
{
    "entryName": "CV3261002100200300000010100000000712345678909.xml",
    "identifier": "CV3261002100200300000010100000000712345678909",
    "succeeded": true,
    "authorizationCode": "AUTH-77",
    "signatureTimeStamp": null,
    "messages": [],
    "packageKind": "dfe",
    "httpStatus": 200,
    "receivedAt": "2026-10-02T12:00:05-01:00"
}
```

Each message carries `source`, `type`, `dateTime`, `code`, `description` and
`location`. `receivedAt` is ISO-8601 with its offset.

## Overwrite policy

An artifact is never overwritten:

- storing the same bytes again returns the same `StoredArtifact`, so a job that
  failed half way can simply run again;
- storing different bytes at an existing path fails with `storage.conflict` and
  leaves the stored file untouched.

Only an authorized document is stored. A rejected one fails with
`storage.not_authorized` before anything is written: corrected and issued
again it keeps its IUD (see [issuance](issuance.md#outcomes)), so the rejected
XML would otherwise hold the canonical path and the authorized attempt would
conflict with it. Keep the evidence of a rejection in the application's own
records.

The existence check and the write run under a lock on the path, taken from the
package cache store (`efatura.cache.store`, keyed under `efatura.cache.prefix`),
so two processes storing the same artifact at the same moment are serialized:
the second sees the first one's file and returns it, or fails with
`storage.conflict` when its bytes differ. A process that waits more than ten
seconds for the lock fails with `storage.unavailable`, which is retryable. The
lock is only as wide as the cache store: use a shared store (redis, database,
memcached) when several servers write to the same disk. A store without locks
stores without one.

## Reading

`ArtifactStore::get(StoredArtifact $artifact)` reads only the path the store
derives from the artifact's IUD and kind on the configured disk. An artifact
whose disk or path does not match the current configuration, or whose file is
gone, fails with `storage.missing`; changing `storage.disk` or `storage.path`
means moving the files as well. Bytes whose sha256 differs from the recorded
checksum fail with `storage.corrupted`.

## Replacing the store

| Contract | Default | Methods |
| --- | --- | --- |
| `Contracts\ArtifactStore` | `Storage\FilesystemArtifactStore` | `put(string $iud, ArtifactKind $kind, string $contents): StoredArtifact`, `get(StoredArtifact $artifact): string` |

Bind another implementation in the container to keep artifacts elsewhere.

## Privacy

Fiscal documents carry personal data of customers: names, tax numbers,
addresses and what they bought. A disk that is served publicly, such as
Laravel's `public` disk linked under `public/storage`, would publish every
stored invoice to anyone who guesses or learns a path, and the paths are
predictable from the IUD. The store refuses to write to a disk whose
configuration sets `'visibility' => 'public'` (`storage.public_disk`). It
cannot see how a disk is exposed otherwise, for example a bucket with a public
policy or a web server pointed at the root of a local disk, so keep the disk
private outside the package as well. The store does not set a visibility on
the files and follows the configuration of the disk. No artifact contains a secret: the signed XML holds
the public certificate only, and the evidence holds no token, middleware key,
client secret, passphrase, header or configuration value.

## Errors

Every failure is a `StorageException` (an `EfaturaException`) whose `errorCode`
and message are the code below.

| Code | Field | Context | Retryable | When |
| --- | --- | --- | --- | --- |
| `storage.not_authorized` | | `iud` | no | The `IssuedDocument` was not authorized by the platform |
| `storage.dfa_mismatch` | `dfa` | `iud`, `dfaIud`, `model` | no | The DFA was rendered for another IUD, or is not the ONLINE model |
| `storage.invalid_iud` | `iud` | | no | The IUD is not an official IUD with a valid check digit |
| `storage.disk_invalid` | `efatura.storage.disk` | `disk` | no | The configured disk cannot be resolved |
| `storage.public_disk` | `efatura.storage.disk` | `disk` | no | The configured disk sets `visibility` to `public` |
| `storage.conflict` | | `path` | no | Different bytes already exist at the path |
| `storage.missing` | | `path` | no | The artifact is not on the disk, or does not belong to the configured disk and path |
| `storage.corrupted` | | `path` | no | The stored bytes no longer match the checksum |
| `storage.unavailable` | | `path` | yes | The disk failed to check, read or write, or the lock on the path was not obtained in time; the filesystem exception or the `LockTimeoutException`, when there is one, is `previous` |

---

[Documentation index](README.md) · [Issuance](issuance.md) · [DFA](dfa.md)
