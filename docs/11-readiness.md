# Readiness

`CheckReadinessAction::handle(bool $remote = false): ReadinessReport` answers
whether this installation can issue documents. By default it never touches the
network. It takes no URL: remote checks reach only the base URL of the configured
driver.

```php
$report = resolve(CheckReadinessAction::class)->handle();

$report->ready();                              // false on any failure or skipped mandatory check
$report->warnings();                           // results that deserve attention but do not block
$report->of(ReadinessCheck::Certificate);      // the results of one check
json_encode($report);                          // check, status, code, field, context, required
$report->toArray();                            // ready and checks, for presenting the report
$report->toJsonDocument();                     // the same, with each context as an object for JSON
```

`toArray()` returns `ready` and one entry per result under `checks`, with the
`check` and `status` as their string values, the `code`, the `field`, whether
it is `required` and the `context`. The context keeps only the keys the checks
document (`days_left`, `driver`, `environment`, `skew_seconds`, `status`), so a
value added to a result later never reaches a presentation by accident.
`toJsonDocument()` is the document `efatura:doctor --json` and the readiness route
print: the same values, with each `context` as an object, so an empty context is
written as `{}` and never as `[]`.

Each `ReadinessResult` has a `check` (`ReadinessCheck`), a `status`
(`Passed`, `Warning`, `Failed`, `Skipped`), a stable `code`, the configuration
`field` concerned when there is one, a `context` of plain values and whether the
check is `required`. `ready()` is false when any result failed or when a required
check was skipped; warnings never block, and a skipped check never counts as
passed.

## Local checks

| Check | Passes when | Otherwise |
| --- | --- | --- |
| Configuration | The configuration loads; `efatura.transmitter.tax_id` and `efatura.software.code`, `name`, `version` are set; the middleware driver has `efatura.transmitter.middleware_key`; the platform driver has `efatura.emitter.tax_id`, the OAuth client ID and secret, a bound `RefreshTokenRepository` and a refresh token for that emitter | `Failed` with `configuration.missing` (one result per missing key), the loading error code (for example `configuration.invalid_length`), `transport.missing_credentials` or `oauth.refresh_token_missing`; `Warning` with the code and field of an error in the `efatura.routes` section, which is read in full here even while the routes are disabled |
| Certificate | `CertificateLoader::load()` reads a valid signing certificate | `Failed` with the certificate error code (`certificate.expired`, `certificate.missing`, `certificate.untrusted`...); `Warning` `readiness.certificate_expiring` less than 30 days before it ends (`days_left` in `context`); `Warning` `readiness.certificate_chain_unverified` in production without `efatura.certificates.ca_bundle_path` |
| Sequences | The sequences table exists on the configured connection (read only) | `Failed` `readiness.sequences_table_missing`; an unknown connection or a database error is `readiness.unexpected_failure` |
| Environment | TLS verification is on for the driver HTTP profile | `Warning` `readiness.tls_verification_disabled` on `efatura.http.<driver>.verify_tls` |

When the configuration itself cannot load, every other local check is `Skipped`
and required, so the report is not ready. The remote checks are `Skipped` too, and
required only when `handle(remote: true)` asked for them.

## Remote checks

Only with `handle(remote: true)`; otherwise they are `Skipped` and not required.

| Check | Request | Result |
| --- | --- | --- |
| Endpoint | `GET` on the base URL of the driver, with the read retries and no credentials | Any HTTP answer below 500 is `Passed` (`status` in `context`); a 5xx still there after the retries is `Warning` `readiness.endpoint_unavailable`; a connection failure is `Failed` with `transport.connection_failed` or `transport.outcome_unknown` |
| Clock | The `Date` header of that answer | `Passed` within 5 minutes; `Warning` `readiness.clock_skew` above 5 minutes; `Failed` above one hour, the online lead the platform accepts (IDT-IMO-LE, p.35); `Warning` `readiness.clock_unverified` without a readable `Date`; `Skipped` and required when the endpoint failed |
| Credentials | Platform driver: an access token for the configured emitter with `cv_ef_dfe_create`, from the bound `AccessTokenProvider` or, failing that, from a bound `RefreshTokenRepository` | `Passed` `readiness.token_obtained`, or `Failed` with the OAuth or transport error code (`transport.missing_credentials` on `efatura.transmitter.oauth` when neither is bound); the middleware driver skips it, since the middleware holds the taxpayer credentials |

The clock thresholds apply in both directions. That is stricter than the
platform, which accepts an issue time up to 24 hours behind (IDT-IMO-GE, p.35): a
local clock an hour slow is still accepted there, but it already eats into the
window a resend or a reconciliation has, so the package reports it.

The remote checks never call taxpayer, software or authorization registries:
the Manual documents no such endpoints.

## Failing closed

Any exception inside a check becomes a `Failed` result with the package error
code, or `readiness.unexpected_failure` for an exception that is not the package's.
The action never throws. Messages of foreign exceptions, which can hold a DSN or a
path, are dropped, and no secret (middleware key, client secret, refresh or access
token, certificate passphrase) ever enters the report.

## From the console

`php artisan efatura:doctor` runs these checks and prints the report, offline
unless `--remote` is given; `--json` prints `toArray()`. Its options, output and
exit codes are described in [console commands](15-console.md).

---

Previous: [Issuance](10-issuance.md) · [Index](00-index.md) · Next: [Currency conversion](12-currency.md)
