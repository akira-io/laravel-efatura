# e-Fatura Playground

A small Laravel 13 application that runs the `akira/efatura` package from this
repository, so every change to the package can be tried in a browser before it
is released. It requires the package through a Composer path repository
(`../`, symlinked), so it always runs the local code.

The playground is not part of the package: it is left out of the Composer
archive (`/playground export-ignore`) and out of the package tooling and test
suite.

## Requirements

- PHP 8.5 with the `curl`, `dom`, `libxml`, `openssl`, `zip` and `pdo_sqlite`
  extensions.
- Composer 2.

No Node.js build step is needed: the pages are plain Blade with one stylesheet.

## Running it

```sh
cd playground
composer install
composer setup
php artisan serve
```

Then open http://127.0.0.1:8000.

`composer setup` copies `.env.example` to `.env` when it is missing, generates
the application key, creates `database/database.sqlite`, runs the migrations
(including the package sequence table) and creates the test signing
certificate. It is safe to run again.

With Laravel Herd, link the directory instead of running `php artisan serve`:

```sh
cd playground
herd link efatura-playground
```

The site is then served at http://efatura-playground.test.

## The test certificate

`php artisan playground:certificate` writes a self-signed RSA 2048 certificate
and its private key to `storage/app/certificates` (`playground.crt` and
`playground.key`), on the `certificates` disk. Pass `--force` to replace them.

This certificate is not issued under ICP-CV and must only be used locally,
against the fake transport or a test repository. The directory is ignored by
Git.

## Pages

| Page | What it does |
| --- | --- |
| `/` | Lists the flows and shows the repository, transport, exchange-rate provider and certificate in use. |
| `/document` | Builds a sample invoice (FTE) with the fluent builder from editable lines, quantities, prices and VAT, numbers it, writes the unsigned XML and validates it against the XSD. Overriding the amount to pay shows how the package refuses totals that do not reconcile. |
| `/sign` | Prepares the document (number, XML, XAdES-BES signature with the test certificate, ZIP) and shows the signed XML with the result of `VerifyXmlSignatureAction` and `ValidateXmlDocumentAction`. |
| `/dfa` | Renders the Documento Fiscal Auxiliar as a PDF, inline or as a download. ONLINE issues the document through the transport first; OFFLINE and OFF prepare it in contingency. |
| `/currency` | Reads the sample prices as EUR and converts the document to CVE with `ConvertDocumentCurrencyAction`, showing both totals and the `PayableAlternativeAmount`. |
| `/doctor` | Runs the readiness checks locally; the button runs the remote checks against the configured driver. |
| `/issue` | Issues the document with `IssueDocumentAction` and shows the authorization code, the time stamp and any platform messages. |

A refused input is shown at the top of the page with the field path or the
package error code.

## Configuration

`.env.example` holds test values for every `EFATURA_*` key the pages need: a
fictitious emitter and transmitter, the registered software, the certificate
paths and a placeholder middleware key. None of them is a real credential.

| Key | Default | Meaning |
| --- | --- | --- |
| `PLAYGROUND_TRANSPORT` | `fake` | `fake` binds the package `FakeTransport`, which authorizes every document without any network access. `configured` uses the driver selected by `EFATURA_DRIVER`. |
| `PLAYGROUND_SERIES` | `PG` | Series written in the sample documents. |
| `EFATURA_ENVIRONMENT` | `test` | Repository of the documents. |
| `EFATURA_EXCHANGE_RATE_PROVIDER` | `fixed` | `fixed` converts EUR at 110.265; set `bcv` to read the Banco de Cabo Verde page when online. |

`config/efatura.php` only overrides the exchange-rate section; every other key
comes from the package configuration and its environment variables (see
[configuration](../docs/02-configuration.md)).

## Switching to homologation

1. Put the transmitter and emitter identities you registered with the
   platform in `.env` (`EFATURA_TRANSMITTER_TAX_ID`, `EFATURA_EMITTER_*`,
   `EFATURA_SOFTWARE_*`).
2. Point `EFATURA_CERTIFICATE_PATH` and `EFATURA_PRIVATE_KEY_PATH` (or a
   PKCS#12 bundle and `EFATURA_PRIVATE_KEY_PASSPHRASE`) at your own signing
   certificate on the `certificates` disk.
3. Set `EFATURA_ENVIRONMENT=homologation` and `PLAYGROUND_TRANSPORT=configured`.
4. For the middleware driver, set `EFATURA_TRANSMITTER_KEY` and
   `EFATURA_MIDDLEWARE_BASE_URL` (and `EFATURA_MIDDLEWARE_CA_BUNDLE_PATH` for
   its self-signed certificate). For the platform driver, set
   `EFATURA_DRIVER=platform` and the OAuth client of the transmitter; the
   platform driver also needs a refresh token repository bound in the
   application (see [transport](../docs/09-transport.md)).
5. Run `/doctor` with the remote checks before issuing.

Every page that numbers a document (`/document`, `/sign`, `/issue` and the
OFFLINE and OFF DFA models) takes the next number from the sequence store,
even when nothing is submitted. In homologation this leaves gaps in the
series, so use a series reserved for the playground. The OFF model always
uses the IUC `<year>/1`; replace it with the IUC the platform assigned before
submitting a contingency document.

Keep real credentials out of Git: `.env` is ignored, and so is
`storage/app/certificates`.

## Tests

```sh
composer test
```

The Pest suite in `tests/` covers every page against the fake transport, an
in-memory sequence store and a certificate generated for each test. It is
separate from the package suite and runs only here.
