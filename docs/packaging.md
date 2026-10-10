# Packaging

The authority receives signed XML inside a ZIP compressed with Deflate, sent as
the `file` part of a `multipart/form-data` request (§10.2, p.67). This page
covers the archive the package builds; sending it belongs to a later stage.

## Building an archive

`Contracts\Packager::package(list<string> $signedXml): PackagedArchive` takes
one or more signed documents, or one or more signed events, and returns the ZIP.
The container binds it to `Packaging\ZipArchivePackager`, which writes the
archive with ext-zip.

Each entry is named after the `Id` of the signed root, never after an argument
of the caller:

- a DFE is written as `{IUD}.xml` (§10.2, p.67);
- an event is written as `{IdEvento}.xml`, the 24-character event ID
  (§10.4, p.70).

Both signature profiles are accepted: for an `internally-detached` document the
name comes from the `Dfe` or `Event` it holds. Entries keep the order they were
given in, sit at the root of the archive (no directories), and are compressed
with Deflate.

`PackagedArchive` carries the ZIP `bytes`, its `kind` (`PackageKind::Documents`
or `PackageKind::Events`, which tells the caller which resource to send it to)
and the list of entry names.

## What is refused

Every failure is a `PackagingException` whose `context` names the position of
the offending entry (`entry`) or the limit, never the XML:

| Code | Cause |
| --- | --- |
| `package.empty` | No XML given |
| `package.too_many_entries` | More than 1000 entries (`Fiscal::MAX_PACKAGE_ENTRIES`) |
| `package.too_large` | More than 10 MiB of XML in total, before compression (`Fiscal::MAX_PACKAGE_BYTES`) |
| `package.unsupported_root` | A root that is not `Dfe`, `Event` or `internally-detached` holding one of them |
| `package.unsigned` | A document without its `ds:Signature` |
| `package.invalid_identifier` | An `Id` that is not a valid IUD (check digit included) or event ID |
| `package.mixed_kinds` | DFE and events in the same archive |
| `package.duplicate_entry` | Two entries with the same `Id` |
| `package.write_failed` | ext-zip could not write the archive |

The Manual says several XML may share one ZIP but sets no count and no size, and
names no ZIP file. The two limits are defensive package limits: 1000 matches
the other list limits of the package, and the archive is never ZIP64. The XML
is parsed with the hardened parser used by the signer and the schema validator.

## Determinism

Every entry carries the same modification time (1 January 1980), so the same
input packaged twice on the same host gives the same bytes. The compressed bytes
still depend on the zlib and libzip versions, so do not compare archives built
on different hosts; compare their entries.

The archive is written to a temporary file in the system temporary directory
and removed before `package()` returns.

## Preparation

`PrepareDocumentAction` and `PrepareEventAction` run the whole chain for one
document or event: emission context, credentials, number and IUD (documents)
or event ID (events), XML, schema validation of the unsigned XML, signature and
archive. They return `Packaging\PreparedDocument` (the numbered document, its
IUD, the unsigned XML, the `SignedXml`, the `PackagedArchive` and whether a
number was reserved) or `Packaging\PreparedEvent` (the event, its ID, the
unsigned XML, the `SignedXml` and the archive). Nothing is sent, stored or
dispatched. See [builders and configuration](builders.md#preparation) for the
order of the steps and [sequences](sequences.md#gaps-udn-and-resuming) for a
failure after the number is reserved.

---

[Documentation index](README.md) · [Signing](signing.md) · [Sequences](sequences.md)
