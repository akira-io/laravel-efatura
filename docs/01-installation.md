# Installation

## Requirements

- PHP 8.5 with the `curl`, `dom`, `libxml`, `openssl` and `zip` extensions.
- Laravel 13.
- A database the sequence table can live in: SQLite, MySQL or PostgreSQL (see
  [document numbers and sequences](06-sequences.md)).
- A signing certificate for the transmitter, as PEM files or a PKCS#12 bundle
  (see [signing](07-signing.md)).
- Either the local e-Fatura middleware, or OAuth client credentials and refresh
  tokens for direct platform submission (see [transport](09-transport.md)).

## Composer

```sh
composer require akira/efatura
```

Package discovery registers `Akira\Efatura\EfaturaServiceProvider`; the
`Akira\Efatura\Facades\Efatura` facade is imported where it is used. The package
requires `laravel/framework`, because it uses foundation classes such as
`FormRequest` and the application helpers, and Guzzle, whose cURL handler the
transport drives directly.

## The install command

```sh
php artisan efatura:install
php artisan migrate
```

`efatura:install` publishes `config/efatura.php` and the
`create_efatura_sequences_table` migration. Run in a terminal without options,
it asks before adding each of `EFATURA_TRANSMITTER_TAX_ID`,
`EFATURA_EMITTER_LED`, `EFATURA_TRANSMITTER_KEY`, `EFATURA_MIDDLEWARE_BASE_URL`
and `EFATURA_ENVIRONMENT` to `.env`, and never overwrites an existing key. It
also runs without questions, for deployments and CI:

```sh
php artisan efatura:install --no-interaction               # config and migration, .env untouched
php artisan efatura:install --write-env --no-interaction   # also append the missing keys to .env
php artisan efatura:install --views --lang                 # also publish the DFA views and the translations
```

Every option, the exit codes and the publish tags behind them are described in
[console commands](15-console.md). Set `EFATURA_DATABASE_CONNECTION` and
`EFATURA_SEQUENCES_TABLE` before running the migration if the defaults do not
fit: the migration reads them when it runs.

## Configuration

Every key of `config/efatura.php`, its type, default and environment variable
is listed in [configuration](02-configuration.md). The five keys the install
command offers select the repository, identify the transmitter and the emitter
LED, and reach the middleware. Signing also needs the certificate keys
(`EFATURA_CERTIFICATES_DISK`, `EFATURA_CERTIFICATE_PATH` and, for a PEM pair,
`EFATURA_PRIVATE_KEY_PATH`) described in [signing](07-signing.md).

## Optional DFA renderers

Rendering the Documento Fiscal Auxiliar as a PDF needs two optional libraries:

```sh
composer require dompdf/dompdf:^3.1 bacon/bacon-qr-code:^3.1
```

`dompdf/dompdf` writes the PDF and `bacon/bacon-qr-code` draws the QR code as
SVG, so neither gd nor imagick is required. `efatura:install` names the ones
that are missing. Without them, building the DFA data still works and rendering
fails with `dfa.renderer_unavailable` (see [DFA](13-dfa.md)).

## Checking the installation

```sh
php artisan efatura:doctor
```

The command runs the readiness checks without touching the network and exits
with `0` when the installation can issue documents (see
[readiness](11-readiness.md)). Problems it reports are listed by code in
[troubleshooting](17-troubleshooting.md).

---

[Index](00-index.md) · Next: [Configuration](02-configuration.md)
