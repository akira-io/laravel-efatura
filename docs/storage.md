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
DFA is given, DFA. Keep the `StoredArtifact` values (for example as columns of
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

A rejected document corrected and issued again keeps its IUD (see
[issuance](issuance.md#outcomes)). Its signed XML, archive and answer differ
from the first attempt, so storing both attempts conflicts. Store the
artifacts once the outcome is final, typically when `$issued->authorized()`.

The existence check and the write are two disk calls: two processes storing
different bytes for the same IUD at the same moment are not serialized by the
store. Run the storage of one document from one job at a time.

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

Fiscal documents carry personal data of customers. Use a private disk that is
never served publicly; the store does not set a visibility and follows the
configuration of the disk. No artifact contains a secret: the signed XML holds
the public certificate only, and the evidence holds no token, middleware key,
client secret, passphrase, header or configuration value.

## Errors

Every failure is a `StorageException` (an `EfaturaException`) whose `errorCode`
and message are the code below.

| Code | Field | Context | Retryable | When |
| --- | --- | --- | --- | --- |
| `storage.invalid_iud` | `iud` | | no | The IUD is not an official IUD with a valid check digit |
| `storage.disk_invalid` | `efatura.storage.disk` | `disk` | no | The configured disk cannot be resolved |
| `storage.conflict` | | `path` | no | Different bytes already exist at the path |
| `storage.missing` | | `path` | no | The artifact is not on the disk, or does not belong to the configured disk and path |
| `storage.corrupted` | | `path` | no | The stored bytes no longer match the checksum |
| `storage.unavailable` | | `path` | yes | The disk failed to check, read or write; the filesystem exception, when there is one, is `previous` |

---

[Documentation index](README.md) · [Issuance](issuance.md) · [DFA](dfa.md)
