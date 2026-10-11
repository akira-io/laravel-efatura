# Documento Fiscal Auxiliar (DFA)

The DFA is the printed companion of an electronic fiscal document (Manual v11,
section 6.13). The issuer hands it to the customer on request, and must hand it
over in contingency. It identifies the document on the e-Fatura platform: the
customer scans its QR code or looks the IUD up at efatura.cv.

The package builds the DFA in two steps. `BuildDfaDataAction` turns an issued or
prepared document into a `DfaData`, and a `DfaRenderer` turns that into a PDF.

```php
$dfa = resolve(BuildDfaDataAction::class)->handle($issued);
$pdf = resolve(DfaRenderer::class)->render($dfa);

return response($pdf->bytes, 200, [
    'Content-Type'        => $pdf->contentType,
    'Content-Disposition' => 'inline; filename="' . $pdf->filename . '"',
]);
```

## Installing the renderers

PDF and QR rendering are optional. Install both libraries in the application:

```bash
composer require dompdf/dompdf:^3.1 bacon/bacon-qr-code:^3.1
```

`dompdf/dompdf` writes the PDF and `bacon/bacon-qr-code` draws the QR code as
SVG, so neither gd nor imagick is required. `efatura:install` names the ones
that are missing, looking for their classes through the autoloader exactly as
the renderers do (`Dfa\DfaLibraries`), so a custom vendor directory is found. Without them, building a `DfaData` still works and rendering
fails with `dfa.renderer_unavailable`.

## Models

The model follows the document and its state on the platform:

| Source | Issue mode | Model | QR code | Extra |
| --- | --- | --- | --- | --- |
| `IssuedDocument` authorized | any | ONLINE | yes | `authorizationCode` in the data |
| `PreparedDocument` | 2 (OFFLINE) | OFFLINE | yes | |
| `PreparedDocument` | 3 (OFF) | OFF | no | the IUC |

An authorized document always prints the ONLINE model, also when it was issued
in contingency: it is no longer pending, so the contingency notices would be
false. A rejected `IssuedDocument`, a `PreparedDocument` issued ONLINE, and a
`PreparedDocument` without an emission context fail with `dfa.not_authorized`:
an ONLINE document gets no DFA before the platform authorizes it.

The three models reproduce the Manual on A4:

- ONLINE: the emitter (NIF, name, address, contact), the totals,
  `Consulta DFE em efatura.cv`, the QR code and the IUD below it.
- OFFLINE: the emitter, the series, type, number and date of the document,
  `EMITIDO EM CONTINGÊNCIA OFFLINE` and `Pendente de Autorização`, the items
  (Código, Descrição, Quantidade, Unidade, Valor Unitário, Taxa Imposto, Valor
  Total), the totals, `Consulta DFE em efatura.cv`, the QR code next to the
  customer, and the notice that the document gives no right to deduct tax until
  it is transmitted and authorized.
- OFF: as OFFLINE, with `EMITIDO EM CONTINGÊNCIA OFF` and the IUC, without the
  QR code and the item code column, and with blank customer lines to fill in by
  hand.

The totals block lists `Nº de Itens`, `Total Líquido` (the net total),
`Encargos`, `Descontos`, `Imposto` and `Total a Pagar` in bold. A withholding
(`Retenção na Fonte`) and a rounding adjustment (`Arredondamento`) are listed
only when they are not zero. When the document has `PayableAlternativeAmount`
entries, a `Conversão cambial` block lists each currency, amount and rate
(`1 EUR = 110,26500 CVE`); see [currency conversion](12-currency.md).

CVE amounts are written as the Manual prints them: thousands separated by a
space and `$` as the decimal separator, always with two decimals (`1 400$00`,
`-0$01`). A unit price keeps its significant decimals, up to the five the
document allows (`0$11027`, `560$00`), so the line reads as it was priced. A
deduction line (`lineTypeCode` D) prints its total with a minus sign
(`-1 234$50`). Foreign amounts use a decimal comma and keep their significant
decimals (`14,60 EUR`, `2 166 JPY`). Quantities and percentages drop
non-significant zeros (`2,5`, `15%`). A line tax is its percentage, its
absolute amount for a stamp tax, or its exemption reason code.

Documents without totals print no totals block: a transport document lists its
lines, and a receipt prints neither lines nor totals.

The Manual leaves the logo optional; the views print none. The Manual defines
no 80 mm format and no second copy, so the package has neither.

## QR code

The QR code holds `{dfa.base_url}/{IUD}` and nothing else, at error correction
level M. The base URL is configured in `config/efatura.php`:

```php
'dfa' => [
    'base_url' => env('EFATURA_DFA_BASE_URL', 'https://pe.efatura.cv/dfe/view'),
],
```

It must use HTTPS without credentials, query strings or fragments, and trailing
slashes are removed (`configuration.unsafe_url`, `configuration.empty_string`
or `configuration.invalid_type` otherwise, when the configuration loads). Any
HTTPS host is accepted, so the QR code can point at a test viewer; the package
does not check that the host belongs to the platform. Keep the default in
production: every printed DFA carries this address, and a customer who scans
it trusts whatever page it opens. The
Manual makes the document public for one hour after its authorization; after
that, the viewer asks for authentication.

## IUC

The OFF model prints the IUC of the contingency. The Manual does not define its
format; the package accepts the shape the contingency data already validates,
four digits, a slash and digits (`2026/15`).

The IUC is read from `emission.contingency.iuc` of the document, which the
validation requires in OFF mode. The second argument of `handle()` is read only
when the document carries none. An argument that differs from the document's
IUC fails with `dfa.iuc_mismatch`, a missing one with `dfa.iuc_required` and a
malformed one with `dfa.invalid_iuc`.

## Large documents

The items of an OFFLINE or OFF DFA flow across as many A4 pages as they need,
with the column headings repeated on each page. dompdf lays out the whole
document in memory: measured on PHP 8.5 with dompdf 3.1, a DFA of 1000 lines
takes about 25 pages and a peak of 275 to 350 MB. Render large contingency DFAs in a process whose `memory_limit`
allows it, for example a queued job, rather than in a web request with the
default 128 MB. The package's regression test renders 300 lines to keep the
suite fast under coverage; the figures above come from 1000.

## Reprints

`DfaData` never reads the clock: the dates come from the document. Building the
data twice for the same document gives equal values, and the PDF renderer
writes its creation and modification dates from the issue instant (in UTC) and
derives the PDF file identifier from the rendered HTML, so a reprint gives the
same bytes.

## Views

The PDF is rendered from the Blade view `efatura::dfa.document`, which includes
`efatura::dfa.online` or `efatura::dfa.contingency` and the partials under
`efatura::dfa.partials`. The package loads them without publishing. To change
the layout, for example to add a logo, publish them:

```bash
php artisan vendor:publish --tag=efatura-views
```

Every document value is printed escaped. Keep it that way in published views:
the values come from the document and its parties. The view receives `$dfa`
(the `DfaData`) and `$qrCode` (an SVG data URI, or null on OFF).

## Renderers

| Contract | Default | Method |
| --- | --- | --- |
| `Contracts\DfaRenderer` | `Dfa\DompdfDfaRenderer` | `render(DfaData): RenderedDfa` |
| `Contracts\QrCodeRenderer` | `Dfa\BaconQrCodeRenderer` | `render(string $payload): string` (SVG) |

`RenderedDfa` holds `bytes`, `contentType` (`application/pdf`), `filename`
(`{IUD}.pdf`), the `iud` it was rendered for and its `model` (an
`EmissionMode`: `Online`, `Offline` or `Off`), copied from the `DfaData`. Bind another implementation in the container to replace either.

The dompdf renderer disables remote resources, embedded PHP and JavaScript, and
accepts only `data:` URIs, which is how the QR code is embedded.
`Dfa\DompdfOptions` builds those options.

dompdf caches the metrics of the fonts it uses and writes temporary files. The
renderer never lets it write into `vendor/`: both go to a directory the
application can write to, with an empty `chroot` beside them.

```php
'dfa' => [
    'cache_path' => env('EFATURA_DFA_CACHE_PATH'),
],
```

| Key | Default | Meaning |
| --- | --- | --- |
| `efatura.dfa.cache_path` (`EFATURA_DFA_CACHE_PATH`) | null | An absolute directory without `..`, `.` or empty segments; null uses `storage/framework/cache/efatura-dompdf` of the application |

The renderer creates the directory and `fonts/`, `tmp/` and `chroot/` under it
(mode 0700) when they are missing. A path that is not absolute fails with
`configuration.unsafe_path` when the configuration loads. When a DFA is
rendered, the directory and each of the three fail with `dfa.cache_unavailable`
when one cannot be created, is owned by another user than the one PHP runs as,
or can be written by its group or by others: another user could otherwise
plant font metrics or swap the QR code temporary file. The effective user is
read from a file PHP creates with `tmpfile()`, so no extension is needed; the
check relies on POSIX ownership and permission bits. On a read-only filesystem,
point it at a writable volume.

## Errors

Every failure is a `DfaException` (an `EfaturaException`) whose `errorCode` and
message are the code below.

| Code | Field | Context | When |
| --- | --- | --- | --- |
| `dfa.not_authorized` | | `iud` | A rejected `IssuedDocument`, or a `PreparedDocument` issued ONLINE or without emission context |
| `dfa.invalid_iud` | `iud` | | The IUD of the document is not an official IUD with a valid check digit |
| `dfa.iuc_required` | `iuc` | `iud` | An OFF document without IUC, and none given |
| `dfa.invalid_iuc` | `iuc` | | An IUC that is not four digits, a slash and digits |
| `dfa.iuc_mismatch` | `iuc` | `iud` | A given IUC that differs from the one on the document |
| `dfa.cache_unavailable` | `efatura.dfa.cache_path` | | The dompdf cache directory, or `fonts/`, `tmp/` or `chroot/` under it, cannot be created, belongs to another user or can be written by its group or others; the filesystem error, when there is one, is `previous` |
| `dfa.renderer_unavailable` | | `package` | `dompdf/dompdf` or `bacon/bacon-qr-code` is not installed |

---

Previous: [Currency conversion](12-currency.md) · [Index](00-index.md) · Next: [Artifact storage](14-storage.md)
