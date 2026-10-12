# Laravel Efatura Documentation

`akira/efatura` is a Laravel package for Cabo Verde e-Fatura fiscal validation,
typed document data, signing, packaging, transport and issuance, built against
the Manual Técnico da Fatura Eletrónica v11.0.

## Guides

The guides are numbered in reading order: installation and configuration first,
then the fiscal domain, the document pipeline from builders to issuance, the
optional features, and the operational guides.

1. [Installation](01-installation.md): Requirements, Composer, the install command and the optional DFA renderers.
2. [Configuration](02-configuration.md): Every key of `config/efatura.php` with its type, default and environment variable.
3. [Fiscal domain](03-fiscal-domain.md): Validated document data, required fields, source precedence and calculation policy.
4. [Builders](04-builders.md): Fluent builders, emitter overrides and preparation.
5. [Identifiers, XML and schema validation](05-xml.md): IUD and event identifiers, the XML writers and XSD validation.
6. [Document numbers and sequences](06-sequences.md): The sequence table, reservations and gaps.
7. [Signing](07-signing.md): Signing credentials, the XMLDSig/XAdES-BES signature and its verification.
8. [Packaging](08-packaging.md): ZIP archives and the preparation actions.
9. [Transport](09-transport.md): Drivers, requests, responses, OAuth and self-billing.
10. [Issuance](10-issuance.md): Issuing documents and events, outcomes and reconciliation.
11. [Readiness](11-readiness.md): Checking an installation before issuing.
12. [Currency conversion](12-currency.md): Exchange rates and converting a foreign currency document to CVE.
13. [Documento Fiscal Auxiliar (DFA)](13-dfa.md): Printing the DFA as a PDF with its QR code.
14. [Artifact storage](14-storage.md): Keeping the signed XML, the archive, the answer and the DFA on a disk.
15. [Console commands](15-console.md): `efatura:install`, `efatura:doctor` and `efatura:validate`: options and exit codes.
16. [Diagnostics routes](16-http.md): Optional JSON routes for readiness and XML validation.
17. [Troubleshooting](17-troubleshooting.md): Package error codes by area, with their fixes.
18. [Compliance with Manual v11](18-compliance-manual-v11.md): Manual v11 requirements mapped to the code and the tests, with the gaps.
19. [Migration guide](19-migration.md): Replaced DTOs, exact values and renamed symbols.

Runnable example: [quick start](examples/quick-start.php), the same code as in
the root [README](../README.md). Runtime configuration source:
[config/efatura.php](../config/efatura.php). Service provider:
[src/EfaturaServiceProvider.php](../src/EfaturaServiceProvider.php). To try the
document flows in a browser, run the [playground](../playground/README.md)
application.

## Validation Scope

The package validates all nine fiscal document payloads and FDC/UDN event payloads through immutable Spatie Data values. The concrete document classes extend the abstract `DocumentData` contract; each owns its official fields. Receipts have no lines or totals, and transport documents have no totals. See [Fiscal domain validation](03-fiscal-domain.md) for entry points, required data, source precedence, and calculation policy.

Host applications remain responsible for persistence, middleware credentials, refresh tokens, and operational audit trails. Keeping the artifacts of an issued document is opt-in through [artifact storage](14-storage.md). Successful domain validation is not a completed, signed, authorized XML document: the XML actions write and validate the unsigned document, the preparation actions number, sign and package it, and the [issuance](10-issuance.md) actions submit it and report the platform answer.

---

Next: [Installation](01-installation.md)
