# Console commands

The package registers three Artisan commands. None of them submits, signs or
stores a fiscal document, and none prints a secret.

| Command | Purpose | Exit `0` | Exit `1` |
| --- | --- | --- | --- |
| `efatura:install` | Publish the configuration, the sequence migration and, on request, the views and translations | Installed, or nothing left to install | `--write-env` without a `.env` file |
| `efatura:doctor` | Run the readiness checks | Ready, warnings included | Any check blocks issuance |
| `efatura:validate` | Validate a signed XML file against the XSD and verify its signature | Schema and signature valid | Invalid document, or a refused path or profile |

## efatura:install

```sh
php artisan efatura:install
php artisan efatura:install --no-interaction
php artisan efatura:install --write-env --no-interaction
php artisan efatura:install --views --lang
php artisan efatura:install --force
```

| Option | Effect |
| --- | --- |
| (none, in a terminal) | Publishes the configuration and the migration, then asks before appending each missing key of `EFATURA_TRANSMITTER_TAX_ID`, `EFATURA_EMITTER_LED`, `EFATURA_TRANSMITTER_KEY`, `EFATURA_MIDDLEWARE_BASE_URL` and `EFATURA_ENVIRONMENT` to `.env`. |
| `--no-interaction` | Publishes the configuration and the migration and leaves `.env` alone. |
| `--write-env` | Appends every missing key to `.env` without asking; fails with exit `1` when there is no `.env`. (`--env` is taken by Laravel for the application environment.) |
| `--views` | Publishes the DFA views, the same files as the `efatura-views` tag. |
| `--lang` | Publishes the translations, the same files as the `efatura-translations` tag. |
| `--force` | Overwrites the configuration, views and translations that already exist. |

Any option, or `--no-interaction`, leaves `.env` untouched unless
`--write-env` is given. A file that already exists is reported and kept;
`--force` never touches an existing `.env` key or the sequence migration, which
is published once. Running the command again changes nothing that is already
installed. It finally names the optional DFA renderers that are missing (see
[DFA](13-dfa.md)).

The same files can be published with `php artisan vendor:publish` and the tags
`efatura-config`, `efatura-migrations`, `efatura-views` and
`efatura-translations`.

## efatura:doctor

```sh
php artisan efatura:doctor            # local checks only, never touches the network
php artisan efatura:doctor --remote   # also the endpoint, clock and credentials checks
php artisan efatura:doctor --json     # the same report as a JSON document
```

`efatura:doctor` runs `CheckReadinessAction` (see [readiness](11-readiness.md))
and prints one row per result with the check, the status, the code and the
configuration field. It exits with `0` when the installation is ready, warnings
included, and with `1` when any result blocks it. Without `--remote` the remote
checks are reported as skipped and the command says how to run them.

`--json` prints the report's `toArray()` and nothing else, so the output can be
piped:

```json
{
    "ready": true,
    "checks": [
        {
            "check": "configuration",
            "status": "passed",
            "code": "readiness.configured",
            "field": null,
            "required": true,
            "context": {"driver": "middleware"}
        }
    ]
}
```

The keys and their order are stable. `code` is the package code, not a message,
so scripts can match on it; there is no translated `message`. Neither output
ever carries a secret, a token, a passphrase or the path of a key: a field names
the configuration key, never its value.

## efatura:validate

```sh
php artisan efatura:validate storage/app/efatura/CV1261002....xml
php artisan efatura:validate signed.xml --profile=detached
php artisan efatura:validate signed.xml --json
```

`efatura:validate` reads the file, refuses a path that does not exist, is not a
regular file, cannot be read or is larger than 5 MiB, and parses it with the
hardened parser. It then validates it against the XSD of the profile and runs
`VerifyXmlSignatureAction` (see [signing](07-signing.md)). `--profile` takes
`enveloped` or `detached`; without it the profile follows the root element
(`internally-detached` means detached). The command never submits, stores or
signs anything.

It exits with `0` only when both the schema and the signature are valid, and with
`1` otherwise, including every refusal. The output always states that the
certificate chain was not verified against ICP-CV. With `--json` it prints:

```json
{
    "valid": true,
    "profile": "enveloped",
    "schema": {"valid": true, "code": null, "violations": [], "violations_total": 0},
    "signature": {
        "valid": true,
        "failures": [],
        "certificate": {"subject": "CN=...", "serial_number": "1715004", "digest": "..."}
    },
    "trust_chain_verified": false
}
```

On libxml before 2.11 the long `ds:X509SerialNumber` of an ICP-CV certificate
is not reported as a violation (see
[schema validation of signed XML](07-signing.md#schema-validation-of-signed-xml)).
`schema.code` is the package code of the failure (`xml.schema_invalid`,
`xml.malformed`, `xml.doctype_forbidden`...), and each violation has its `line`,
`column` and redacted `message`. Only the first 100 violations are listed
(`XmlValidation::MAX_PRESENTED_VIOLATIONS`); `violations_total` counts them all,
and the text output says how many it left out. XML refused by the parser has a `null`
`profile` and `signature`. A document the schema refuses keeps its `profile` and
has a `null` `signature`: the signature is not checked, so a document built to
make verification slow costs no more than its schema validation, and the text
output says "Signature: not checked". A refused path or profile prints only
`{"valid": false, "error": "<reason>"}`, with `file_missing`,
`file_not_regular`, `file_unreadable`, `file_too_large` or `profile_invalid`.

When the bundled official schemas cannot be used (an `OfficialArtifactException`,
`artifacts.*`), the document is not validated at all: the command exits with `1`,
says the installation must be repaired instead of calling the document invalid,
and with `--json` prints
`{"valid": false, "error": "artifacts_unavailable", "code": "artifacts.<reason>"}`.
See [troubleshooting](17-troubleshooting.md#official-artifacts).

The same validation is available over HTTP through the optional
[diagnostics routes](16-http.md).

---

Previous: [Artifact storage](14-storage.md) · [Index](00-index.md) · Next: [Diagnostics routes](16-http.md)
