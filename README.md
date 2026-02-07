# akira/efatura

Fiscal compliance engine for Cabo Verde e-Fatura (DNRE) focused on XML, IUD, and middleware communication.

## Overview

This package implements the Cabo Verde e-Fatura requirements defined by the `Manual Tecnico da Fatura Eletronica v10.0.` It is scoped to `DNRE` compliance only and does not attempt to cover other jurisdictions or fiscal regimes.

## Key design principles

- Middleware-first: all submissions and status checks flow through authorized middleware.
- Data-first: input is structured fiscal Data, output is compliant XML.
- Contracts: optional capabilities (PDF, QR, transport) are expressed through contracts.
- Laravel-first: configuration and integration follow Laravel conventions.

## What the package does

- Builds compliant XML documents for e-Fatura flows.
- Generates IUD values and validates their check digit.
- Prepares payloads for middleware submission and status updates.
- Normalizes error reporting from validation and middleware responses.

## What the package does NOT do

- Render PDFs or QR codes by default.
- Provide UI screens or storage for issued documents.
- Calculate taxes, pricing, or accounting totals.
- Connect directly to the tax authority without middleware.

## Installation

```bash
composer require akira/efatura
```

## Configuration

All values are read from environment variables. Configure them in your `.env` and publish or reference `config/efatura.php` as needed.

- `EFATURA_TRANSMITTER_NIF`: issuer tax identifier (NIF).
- `EFATURA_TRANSMITTER_LED`: issuer LED code assigned by DNRE.
- `EFATURA_TRANSMITTER_KEY`: middleware credential or shared key.
- `EFATURA_MIDDLEWARE_BASE_URL`: middleware base URL.
- `EFATURA_MIDDLEWARE_ENV`: `sandbox` or `production`.

## Core concepts

### IUD

The `IUD` is a unique identifier defined by the DNRE specification. It is assembled from issuer and document Data (such as issuer identifiers, document type, serial and sequence, and issue date) and ends with a Luhn check digit (DV). The package derives the IUD from Data inputs and appends the Luhn DV deterministically.

### Issue Modes

- `Normal`: online issuance with immediate middleware submission.
- `Offline`: issuance without live submission, followed by later transmission.
- `Off`: issuance intentionally disabled by issuer policy or environment.
- `Contingency`: issuance when middleware is unavailable, flagged for later reconciliation.

## Emitting an invoice

Example flow (no implementation yet):

1. Collect invoice Data (issuer, customer, lines, totals, taxes, issue date).
2. Generate the IUD and apply the Luhn DV.
3. Build the XML payload and validate it against the applicable XSD.
4. Package and sign (when required) for middleware transport.
5. Submit to middleware and record the response and status.

## Credit notes

Credit notes must reference the original invoice IUD. The reference is part of the fiscal Data and must be kept immutable for audit traceability.

## Middleware communication

This package does not communicate directly with the tax authority. All submissions, status checks, and acknowledgements are performed through authorized middleware using configured credentials.

## PDF and QR generation philosophy

PDF and QR generation are optional and contract-based. The package provides compliant Data and identifiers but does not render or impose any layout, styling, or visual output.

## Contracts overview

Contracts describe integration points for optional capabilities such as middleware transport, signing and packaging, and PDF or QR rendering. Implementations are left to the host application or external packages to preserve flexibility and compliance requirements.

## Error handling philosophy

Validation errors are surfaced before submission. Middleware and transport errors are mapped into consistent error structures and are never silently corrected. All error responses are intended to be auditable and traceable.

## Testing philosophy

Testing focuses on Data validation, IUD calculation, XML generation, and schema compliance. Rendering, UI, and middleware infrastructure are out of scope for automated tests in this package.

## Compliance notes

- UTF-8 encoding is required for all XML content.
- XML must validate against DNRE XSD definitions.
- Packaging may require ZIP and legal flags for issue mode.
- All issuance modes must be correctly flagged for audit and reconciliation.

## Credits
- [kidiatoliny](https://github.com/kidiatoliny)
- [All Contributors](../../contributors)

## License

MIT

## Final note

`akira/efatura` is a Laravel-first fiscal engine focused on compliant Data, identifiers, and middleware communication for Cabo Verde e-Fatura.
