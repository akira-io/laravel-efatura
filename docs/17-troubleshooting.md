# Troubleshooting

Every exception the package throws extends `EfaturaException` and carries a stable
`errorCode`, the configuration key concerned in `field` when there is one, and a
`context` of plain values. The code is the lookup key of this page. Messages never
hold a secret, a path or a passphrase, so the code and the field are what you have
to work with.

Start with the readiness report. It runs the same loaders and checks as an
issuance would and lists every code it finds:

```sh
php artisan efatura:doctor            # local checks, no network
php artisan efatura:doctor --remote   # also the endpoint, clock and credentials
```

After changing `.env` on an installation that caches its configuration, run
`php artisan config:clear` (or `config:cache` again) before checking anything: a
cached configuration keeps the old values.

## Configuration

`ConfigurationException` is raised while the package configuration loads: the
`routes` section on every boot, the rest the first time the package resolves
`EfaturaConfig` (any action, `efatura:doctor`). The `field` names the key, for
example `efatura.transmitter.middleware_key`. See
[Configuration](02-configuration.md) for every key and its variable.

| Code | Meaning | Fix |
| --- | --- | --- |
| `configuration.missing` | A key an operation needs is null: `efatura.transmitter.tax_id` or `efatura.software.code`, `name`, `version` when the emission context comes from the configuration, or a credential key reported by readiness | Set the key named by `field` (`EFATURA_TRANSMITTER_TAX_ID`, `EFATURA_SOFTWARE_*`), or pass an explicit emission context to the builder |
| `configuration.invalid_type` | A value of the wrong type: a section that is not an array, a boolean that is not a boolean, a middleware list that is not a list of nonempty strings, a bad exchange-rate map, a `callback` that is not an existing class | Correct the value at `field`; boolean variables such as `EFATURA_HTTP_VERIFY_TLS` must read `true` or `false`, not `1`, `0` or `yes` |
| `configuration.empty_string` | A string that is empty after trimming | Remove the variable to fall back to the default, or give it a value |
| `configuration.invalid_integer` | An integer below its minimum (timeouts, concurrency and the cache TTL at least 1, retries and retry delay at least 0, `bcv.max_publication_age_days` at least 0), or `routes.max_upload_kilobytes` above 5120 | Use digits only (`30`, not `30.0` or `-1`) within the limit |
| `configuration.invalid_environment` | `efatura.environment` is not `test`, `homologation`, `production`, `3`, `2`, `1` or an `Environment` case | Fix `EFATURA_ENVIRONMENT` |
| `configuration.invalid_driver` | `efatura.driver` is not `middleware` or `platform` | Fix `EFATURA_DRIVER` |
| `configuration.invalid_tax_id` | `efatura.emitter.tax_id` or `efatura.transmitter.tax_id` is not nine digits starting with 1 to 9 | Fix `EFATURA_EMITTER_TAX_ID` or `EFATURA_TRANSMITTER_TAX_ID` |
| `configuration.invalid_led` | `efatura.emitter.led` is not a decimal from 1 to 99999 | Fix `EFATURA_EMITTER_LED` (no leading zero) |
| `configuration.invalid_length` | `efatura.transmitter.middleware_key` does not have exactly 64 characters | Copy the full transmitter key into `EFATURA_TRANSMITTER_KEY`, without quotes or a trailing newline |
| `configuration.invalid_format` | The middleware key holds a space, a control character or anything outside printable ASCII | Same as above |
| `configuration.invalid_identifier` | `efatura.database.sequences_table` is not an SQL identifier (letters, digits, underscores, not starting with a digit) | Fix `EFATURA_SEQUENCES_TABLE` before migrating |
| `configuration.unsafe_url` | A base URL, `transmitter.oauth.issuer_url` or `dfa.base_url` that is not HTTPS, or carries credentials, a query string (only the BCV page may) or a fragment | Use a plain `https://` URL; the middleware and platform URLs end with `/v1` |
| `configuration.fixed_endpoint` | `efatura.http.world_bank.base_url` holds a value; that endpoint is fixed | Delete the key and `EFATURA_WORLD_BANK_BASE_URL` |
| `configuration.unsafe_path` | A disk-relative path (certificates, `storage.path`) with a leading slash, `..`, `.`, an empty segment, a backslash or a control character; or an absolute path (`dfa.cache_path`, `http.*.ca_bundle_path`) that is relative or has such segments | Write disk paths as `efatura/certificate.p12` and local paths as absolute paths |
| `configuration.unreadable_path` | `efatura.http.ca_bundle_path` or `efatura.http.<client>.ca_bundle_path` is not a readable file | Check the path in `EFATURA_HTTP_CA_BUNDLE_PATH` or `EFATURA_MIDDLEWARE_CA_BUNDLE_PATH` and the file permissions of the PHP user |
| `configuration.conflicting_tls` | A client has a `ca_bundle_path` and `verify_tls` false | Keep `verify_tls` true when a bundle is set; the bundle already trusts the self-signed middleware certificate |
| `configuration.invalid_exchange_rate_provider` | `exchange_rates.provider` is not `bcv`, `world_bank`, `fixed` or `callback` | Fix `EFATURA_EXCHANGE_RATE_PROVIDER` |
| `configuration.invalid_rate_type` | `bcv.rate_type` is not `buy` or `sell`, or `fixed.rate_type` is not an `ExchangeRateType` value | Fix `EFATURA_BCV_RATE_TYPE` or the fixed section |
| `configuration.invalid_observation_year` | `world_bank.observation_year` is not `previous` or `request` | Fix `EFATURA_WORLD_BANK_OBSERVATION_YEAR` |
| `configuration.unprotected_routes` | The diagnostics routes are enabled with an empty `routes.middleware` list; the application does not boot | Add the middleware that authenticates and authorizes the caller, or set `EFATURA_ROUTES_ENABLED=false` |
| `configuration.invalid_route_prefix` | `routes.prefix` is not a URI path of segments starting with a letter or digit (no parameters) | Use a value such as `efatura` or `internal/efatura` |
| `configuration.invalid_route_name` | `routes.name` does not end with a dot, or holds other characters than letters, digits, `_`, `-` and dots | Use a value such as `efatura.` |

## Certificates and signing

`CertificateException` comes from the certificate loader, whose `field` names
the configuration key, never the file path. See [Signing](07-signing.md).

| Code | Meaning | Fix |
| --- | --- | --- |
| `certificate.missing` | `efatura.certificates.certificate_path` (or `private_key_path` for PEM) is not set | Set `EFATURA_CERTIFICATE_PATH`, and `EFATURA_PRIVATE_KEY_PATH` for a PEM pair |
| `certificate.unreadable` | The disk cannot be resolved, or the file is missing or empty on it | Check `EFATURA_CERTIFICATES_DISK` against `config/filesystems.php` and that the path is relative to that disk |
| `certificate.invalid` | The file is not a certificate, key or PKCS#12; a PKCS#12 was given together with a `private_key_path`; or the content is a `file://` reference | Point to the actual credential file; leave `EFATURA_PRIVATE_KEY_PATH` empty for a `.p12` or `.pfx` |
| `certificate.passphrase_invalid` | The passphrase does not open the PKCS#12 or the encrypted PEM key | Fix `EFATURA_PRIVATE_KEY_PASSPHRASE`; it is used exactly, whitespace included |
| `certificate.pkcs12_unsupported` | The PKCS#12 is encrypted with an algorithm the installed OpenSSL no longer loads, such as RC2 under OpenSSL 3 without the legacy provider | Re-export the `.p12` or `.pfx` with AES encryption (for example `openssl pkcs12 -export` with `-keypbe AES-256-CBC -certpbe AES-256-CBC`) and point `EFATURA_CERTIFICATE_PATH` at the new file |
| `certificate.key_mismatch` | The private key does not belong to the certificate | Use the key issued with that certificate |
| `certificate.key_unsupported` | The key is not RSA, or is shorter than 2048 bits | Obtain an RSA certificate of at least 2048 bits |
| `certificate.not_yet_valid` | The certificate starts after the current time | Wait for its start date, or check the server clock |
| `certificate.expired` | The certificate has ended | Renew it; readiness warns 30 days before (`readiness.certificate_expiring`) |
| `certificate.usage_invalid` | Its `keyUsage` allows neither `digitalSignature` nor `nonRepudiation` | Use a signing certificate |
| `certificate.untrusted` | `efatura.certificates.ca_bundle_path` is set and the bundle does not trust the certificate, or the bundle holds no certificate, a damaged one or another kind of PEM block | Put the ICP-CV CA certificates (and only certificates) in that PEM file |

`SignatureException` is raised while signing:

| Code | Meaning | Fix |
| --- | --- | --- |
| `signature.unsupported_root` | The root is not `Dfe` or `Event` in the e-Fatura namespace | Sign the XML produced by the [XML actions](05-xml.md) |
| `signature.missing_id` | The root `Id` is not a valid IUD (for `Dfe`) or event ID (for `Event`) | Build the document through the package actions, which write the `Id` |
| `signature.already_signed` | The document already holds a `ds:Signature` | Sign the unsigned XML once |
| `signature.failed` | OpenSSL could not sign, or the result did not verify | Run `php artisan efatura:doctor` to check the certificate and key |

The following codes are not thrown: they appear in the `failures` of a signature
verification, in `php artisan efatura:validate` and in the validate route.

| Code | Meaning |
| --- | --- |
| `signature.missing` | The document has no `ds:Signature` |
| `signature.multiple` | It has more than one |
| `signature.placement` | The signature is not where either profile puts it, or other elements sit beside it or the signed document |
| `signature.structure_unexpected` | The signature holds elements other than one `SignedInfo`, `SignatureValue`, `KeyInfo` and `Object` with the content the signer writes |
| `signature.algorithm_unsupported` | An algorithm the signer never writes |
| `signature.reference_unexpected` | Unexpected references, or an `Id` missing or used twice |
| `signature.digest_mismatch` | A reference digest does not match: the content changed after signing |
| `signature.certificate_invalid` | `KeyInfo` holds no readable certificate |
| `signature.key_unsupported` | The certificate key is not RSA |
| `signature.certificate_mismatch` | `CertDigest` is not the SHA-256 of the `KeyInfo` certificate |
| `signature.value_invalid` | `SignatureValue` does not verify against that certificate |

A document that fails here was altered or was not signed by the package; sign
the original XML again.

## Sequences

`SequenceException` comes from the number reservation. See
[Sequences](06-sequences.md).

| Code | Meaning | Fix |
| --- | --- | --- |
| `sequence.inside_transaction` | A reservation (or `PrepareDocumentAction`) ran while its connection was inside a transaction | Prepare before opening a transaction on that connection, or set `EFATURA_DATABASE_CONNECTION` to a connection of its own; in tests bind `InMemorySequenceStore` or keep that connection out of `$connectionsToTransact` |
| `sequence.unavailable` | A database failure, or a lock still held after 5 attempts; `retryable` is true | Retry; on SQLite shared by several workers, set a `busy_timeout`. A failure during `COMMIT` may leave a gap, which an UDN event closes |
| `sequence.exhausted` | The counter of that scope reached 999 999 999 | No further number exists for that emitter, year, LED and document type |

A missing table shows up as `readiness.sequences_table_missing`; see below.

## XML

`SchemaValidationException` comes from the hardened parser and the XSD
validation. See [XML](05-xml.md).

| Code | Meaning | Fix |
| --- | --- | --- |
| `xml.schema_invalid` | The document does not follow the XSD of the profile, or its root is not one the profile accepts; the violations carry the line and message. A long decimal `ds:X509SerialNumber`, which libxml before 2.11 refuses as `xs:integer`, is not reported | Correct the data the violations point at, then prepare again |
| `xml.malformed` | The XML is not well formed | Check the file was not truncated or re-encoded |
| `xml.doctype_forbidden` | The document holds a document type declaration | Remove the `DOCTYPE`; it is never accepted |
| `xml.too_complex` | The document holds more than 100 000 elements, or an element with more than 16 namespace declarations on itself and its ancestors | Check the file is a single e-Fatura document; no real document comes near either limit |
| `xml.external_resource` | The schema asked for a file or URL outside the bundled official artifacts | Reinstall the package so `resources/official-artifacts.json` and the XSD files match |

## Official artifacts

`OfficialArtifactException` means the XSD files and catalogs bundled in
`resources/` do not match `resources/official-artifacts.json`, so nothing is
validated against them. It is a fault of the installation, never of a document:
`efatura:validate` reports it apart from an invalid document and the validate
route answers 503. Reinstall the package (`composer reinstall akira/efatura`)
and check that no deployment step rewrites files under `vendor/`.

| Code | Meaning |
| --- | --- |
| `artifacts.missing_root` | The `resources` directory of the package cannot be found or is not a directory |
| `artifacts.invalid_manifest` | `official-artifacts.json` is not valid JSON, or lacks its `files` or `signature_profiles` |
| `artifacts.unknown_profile` | The manifest has no entry XSD for the signature profile asked for |
| `artifacts.unknown_or_unsafe_path` | A file asked for is not listed in the manifest, or its path is not a plain relative path |
| `artifacts.invalid_checksum` | The manifest lists a file with a checksum that is not a SHA-256 |
| `artifacts.invalid_size` | The manifest lists a file with a size that is not an integer of zero or more |
| `artifacts.missing_or_unreadable` | A listed file is missing, unreadable or not a regular file |
| `artifacts.symbolic_link` | A listed file, or a directory on its path, is a symbolic link |
| `artifacts.size_mismatch` | A listed file does not have the size the manifest records |
| `artifacts.checksum_mismatch` | A listed file does not have the SHA-256 the manifest records |
| `artifacts.invalid_schema` | The listed XSD files match the manifest but libxml cannot compile them |

## Packaging

`PackagingException` comes from `ZipArchivePackager` before anything is sent; the
`context` names the `entry` (its position in the list) when one entry is at
fault. See [packaging](08-packaging.md).

| Code | Meaning | Fix |
| --- | --- | --- |
| `package.empty` | No XML given | Pass at least one signed document or event |
| `package.too_many_entries` | More than 1000 entries | Split the archive |
| `package.too_large` | More than 10 MiB of XML before compression | Split the archive |
| `package.unsupported_root` | A root that is not `Dfe`, `Event` or `internally-detached` holding one of them | Package only signed e-Fatura documents and events |
| `package.unsigned` | A root without a `ds:Signature` child | Sign the XML before packaging it |
| `package.invalid_identifier` | An `Id` that is not a valid IUD or event ID | Build the document through the package actions, which write the `Id` |
| `package.mixed_kinds` | Documents and events in the same archive | Package them separately |
| `package.mixed_repositories` | Identifiers of more than one repository | Package one environment per archive |
| `package.mixed_self_billing` | Self-billed and regular documents together | Package them separately |
| `package.missing_emitter` | An event without a non-empty `EmitterTaxId` | Build the event through the package actions |
| `package.duplicate_entry` | Two entries with the same `Id` | Remove the duplicate |
| `package.write_failed` | ext-zip could not write the archive in the temporary directory | Check the free space and permissions of the temporary directory |

## Transport and OAuth

`TransportException` carries the failure in `$failure`, and `retryable` and
`mayHaveBeenProcessed` tell what to do next. See [Transport](09-transport.md) and
[Issuance](10-issuance.md).

`transport.outcome_unknown` means the request left the machine and no readable
answer came back: the platform may have authorized the document. Reconcile by IUD
with `ReconcileDocumentAction` before anything else. Found means authorized; not
found means send the same `PreparedDocument` again. Never prepare a new number,
and never issue an UDN for that number, until the lookup has answered.
`transport.unexpected_response` and `transport.response_too_large` are handled
the same way.

| Code | Meaning | Fix |
| --- | --- | --- |
| `transport.outcome_unknown` | A 3xx or 5xx, or a connection broken after the request left | Reconcile by IUD first, as above |
| `transport.unexpected_response` | A successful status whose body is not the documented answer (HTML from a proxy, JSON, malformed XML) | Reconcile by IUD first; check that the base URL points at the middleware or platform and not at a proxy page |
| `transport.response_too_large` | A successful status with a body over 5 MiB | Reconcile by IUD first |
| `transport.connection_failed` | DNS failure, connection refused, connect timeout or TLS handshake; nothing was delivered | Check `efatura.http.<driver>.base_url`, that the middleware runs, and its CA bundle; resend the same `PreparedDocument` |
| `transport.rate_limited` | 429 | Resend the same `PreparedDocument` later |
| `transport.unauthorized` | 401 or 403 | Check `EFATURA_TRANSMITTER_KEY` (middleware) or the taxpayer consent and token (platform) |
| `transport.http_error` | 404, 405 or 415 on a submission, or a lookup answered outside 2xx | Check the base URL ends with `/v1` and names the right host |
| `transport.payload_too_large` | 413 | Send fewer entries per archive; see [Packaging](08-packaging.md) |
| `transport.missing_credentials` | No middleware key, no OAuth client, no token provider or refresh token repository, or no `efatura.emitter.tax_id` for a self-billing request; nothing was sent | Set the key named in `field`, or bind a `RefreshTokenRepository` for the platform driver |
| `transport.repository_mismatch` | The archive belongs to another repository than `efatura.environment` | Prepare the document again under the right environment, or restore `EFATURA_ENVIRONMENT` |
| `transport.mixed_emitters` | An archive of more than one emitter on the platform driver | Package one emitter per archive |
| `transport.rejected` | A self-billing authorization answered `succeeded` false; the platform messages are in `$messages` | Read the messages and correct the request |

`OAuthException` happens before the fiscal request leaves, so the document was
never processed.

| Code | Meaning | Fix |
| --- | --- | --- |
| `oauth.discovery_failed` | The OpenID configuration could not be read, or names a token endpoint outside the issuer | Check `EFATURA_OAUTH_ISSUER_URL` and outbound access to the issuer host |
| `oauth.refresh_token_missing` | The repository holds no refresh token for that NIF | Have the taxpayer grant access and store the refresh token |
| `oauth.token_refused` | The token endpoint answered 400 or 401: revoked consent, a lost rotated token, or a wrong client | Check `EFATURA_TRANSMITTER_CLIENT_ID` and `EFATURA_TRANSMITTER_CLIENT_SECRET`; otherwise the taxpayer must grant access again |
| `oauth.token_unavailable` | The token endpoint was unreachable, or another renewal held the lock too long; `retryable` is true | Resend the same `PreparedDocument` later |
| `oauth.unexpected_response` | Any other answer, or a grant with invalid tokens or expiry | Check the issuer URL points at the e-Fatura issuer |

## Readiness

`efatura:doctor` and the readiness route also report codes of their own. Failed
results block the report; warnings do not. See [Readiness](11-readiness.md).

| Code | Status | Meaning | Fix |
| --- | --- | --- | --- |
| `readiness.sequences_table_missing` | Failed | The sequences table does not exist on the configured connection | Run `php artisan efatura:install` to publish the migration, set `EFATURA_DATABASE_CONNECTION` and `EFATURA_SEQUENCES_TABLE`, then `php artisan migrate` |
| `readiness.unexpected_failure` | Failed | An exception from outside the package, such as an unknown database connection | Check the connection named by `EFATURA_DATABASE_CONNECTION` and the database itself |
| `readiness.skipped` | Skipped | The check did not run, usually because the configuration failed to load; a required skipped check blocks | Fix the configuration code reported in the same report |
| `readiness.clock_skew` | Warning or Failed | The local clock differs from the `Date` of the endpoint by more than 5 minutes (warning) or one hour (failed) | Synchronize the server clock (NTP) |
| `readiness.clock_unverified` | Warning | The endpoint answer had no readable `Date` header | Nothing blocks; check the clock by other means |
| `readiness.endpoint_unavailable` | Warning | The endpoint answered 5xx after the retries | Check the middleware or platform status and retry later |
| `readiness.certificate_expiring` | Warning | The certificate ends in less than 30 days (`days_left` in `context`) | Renew it |
| `readiness.certificate_chain_unverified` | Warning | Production without `efatura.certificates.ca_bundle_path` | Set `EFATURA_CA_BUNDLE_PATH` to an ICP-CV bundle |
| `readiness.tls_verification_disabled` | Warning | `efatura.http.<driver>.verify_tls` is false | Turn verification on and set a CA bundle for the middleware |

## Exchange rates and BCV

`ExchangeRateException` comes from the rate providers and the currency
conversion. Only `exchange_rate.provider_unavailable`, and
`exchange_rate.date_unavailable` for the BCV while the requested day is today, are
retryable. See [Currency](12-currency.md).

| Code | Meaning | Fix |
| --- | --- | --- |
| `exchange_rate.provider_unavailable` | Connection failure, a non-2xx answer, or a callback that threw | Retry later; check outbound access to `www.bcv.cv` or `api.worldbank.org` |
| `exchange_rate.response_invalid` | A body too large, a page or JSON that cannot be read with certainty, a quote without provider name or evidence | Retry later; if it persists the source page changed, so use another provider |
| `exchange_rate.currency_unsupported` | A currency outside the official catalog, not published by the BCV, not mapped for the World Bank or absent from the fixed rates | Add the mapping or fixed rate, or choose another provider |
| `exchange_rate.pair_mismatch` | A quote for another pair or rate type | Request a rate type the provider supports (BCV: buy or sell; World Bank: reference) |
| `exchange_rate.rate_invalid` | A rate that is not a positive decimal or rounds to zero | Fix the fixed rate or the callback |
| `exchange_rate.date_unavailable` | The BCV page still shows an earlier day with `max_publication_age_days` at zero, no World Bank observation for the year, or a quote dated after the request | Retry later, raise `EFATURA_BCV_MAX_PUBLICATION_AGE_DAYS`, or set `EFATURA_WORLD_BANK_OBSERVATION_YEAR=previous` |
| `exchange_rate.date_invalid` | A BCV publication dated after the requested day | Request the day of the publication or later |
| `exchange_rate.stale` | A BCV publication older than `max_publication_age_days` | Retry when the BCV publishes, or raise the limit |
| `exchange_rate.source_required` | A source URL that is not HTTPS on the expected host, or carries credentials or a fragment | Fix `EFATURA_BCV_BASE_URL`, or the source a callback returns |
| `exchange_rate.invoice_invalid` | A document without totals, or a converted document the fiscal validation refuses | Build totals before converting; read the previous `ValidationException` |
| `exchange_rate.alternatives_conflict` | The source document already has a payable alternative amount | Convert the document without that alternative |

## DFA

`DfaException` comes from building and rendering the auxiliary document. See
[DFA](13-dfa.md).

| Code | Meaning | Fix |
| --- | --- | --- |
| `dfa.renderer_unavailable` | `dompdf/dompdf` or `bacon/bacon-qr-code` is not installed | `composer require dompdf/dompdf:^3.1 bacon/bacon-qr-code:^3.1` |
| `dfa.cache_unavailable` | The dompdf cache directory, or `fonts/`, `tmp/` or `chroot/` under it, cannot be created, belongs to another user or is writable by group or others | Fix the ownership and mode of `EFATURA_DFA_CACHE_PATH` (owned by the PHP user, closed to group and others) |
| `dfa.not_authorized` | A rejected `IssuedDocument`, or a `PreparedDocument` issued ONLINE or without emission context | Render the DFA from the authorized `IssuedDocument` |
| `dfa.invalid_iud` | The IUD is not an official IUD with a valid check digit | Render a document the package prepared |
| `dfa.iuc_required` | An OFF document without IUC | Pass the IUC |
| `dfa.invalid_iuc` | An IUC that is not four digits, a slash and digits | Correct the IUC |
| `dfa.iuc_mismatch` | The given IUC differs from the one on the document | Pass the IUC of that document |

## Storage

`StorageException` comes from `StoreIssuedArtifactsAction` and the artifact
store. See [Storage](14-storage.md).

| Code | Meaning | Fix |
| --- | --- | --- |
| `storage.disk_invalid` | `efatura.storage.disk` cannot be resolved | Name a disk of `config/filesystems.php` in `EFATURA_STORAGE_DISK` |
| `storage.public_disk` | The disk sets `visibility` to `public` | Use a private disk |
| `storage.not_authorized` | The `IssuedDocument` was not authorized | Store only authorized documents |
| `storage.dfa_mismatch` | The DFA was rendered for another IUD, or is not the ONLINE model | Render the DFA from the same `IssuedDocument` |
| `storage.invalid_iud` | The IUD is not an official IUD with a valid check digit | Store documents the package prepared |
| `storage.conflict` | Different bytes already exist at the path | Investigate: the same IUD was stored with other content |
| `storage.missing` | The artifact is not on the disk, or does not belong to the configured disk and path | Check whether `EFATURA_STORAGE_DISK` or `EFATURA_STORAGE_PATH` changed since it was stored |
| `storage.corrupted` | The stored bytes no longer match the checksum | Restore the file from a backup |
| `storage.unavailable` | The disk failed, or the lock on the path was not obtained; `retryable` is true | Retry; check the disk and the cache store |

## Money

`EfaturaValidationException` from the `FiscalMoney` API:

| Code | Meaning | Fix |
| --- | --- | --- |
| `money.invalid_currency` | A currency code outside the official catalog | Use an ISO 4217 code of the catalog |
| `money.currency_mismatch` | A `Money` amount in another currency than the one given | Pass amounts in a single currency, or convert first |
| `money.invalid` | A float amount | Pass the amount as a string, an integer or a `BigDecimal` |

## Diagnostics routes

See [Diagnostics routes](16-http.md).

- The routes return 404 or are missing from `php artisan route:list`: they are
  registered only when `efatura.routes.enabled` is true (`EFATURA_ROUTES_ENABLED`).
  With cached routes the package registers nothing; run `php artisan route:clear`
  or `route:cache` again after enabling them, and `config:clear` if the
  configuration is cached.
- The application does not boot after enabling them: an empty
  `efatura.routes.middleware` fails with `configuration.unprotected_routes`. Add
  the middleware that authenticates and authorizes the caller. The package never
  serves them unprotected.
- `GET {prefix}/readiness` answers 503: the report is not ready. The body lists the
  results; look their codes up on this page.
- `POST {prefix}/validate` answers 422 with `errors`: the upload is missing, larger
  than `routes.max_upload_kilobytes`, of another type than XML or plain text, or
  has an unknown `profile`. It answers 422 with `valid: false` and `schema.code`
  when the document itself fails.
- A 413, a 400 or any status other than 200, 422 and 503 does not come from the
  package. A 413 usually means the body exceeded PHP `post_max_size` or
  `upload_max_filesize`, or a web server limit, before Laravel saw it; a 401 or
  403 comes from your own middleware.

## Console exit codes

See [Console](15-console.md).

| Command | Exit `0` | Exit `1` |
| --- | --- | --- |
| `php artisan efatura:doctor` | Ready, warnings included | Any failed result, or a required check skipped |
| `php artisan efatura:validate <file>` | Valid schema and signature | Invalid, or the file was refused (missing, unreadable, above 5 MiB, unknown profile) |
| `php artisan efatura:install` | Installed | `--write-env` found no `.env` |

---

Previous: [Diagnostics routes](16-http.md) · [Index](00-index.md) · Next: [Compliance with Manual v11](18-compliance-manual-v11.md)
