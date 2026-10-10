# Laravel Efatura Documentation

`akira/efatura` is a Laravel package for Cabo Verde e-Fatura fiscal validation, typed document data, configuration, and middleware-oriented integration.

## Guides

- Installation and configuration: see the root [README.md](../README.md).
- Runtime configuration: see [config/efatura.php](../config/efatura.php).
- Fluent builders and emitter overrides: see [builders and configuration](builders.md).
- Identifiers, XML output and XSD validation: see [identifiers, XML and schema validation](xml.md).
- Document numbers, the sequence table and gaps: see [document numbers and sequences](sequences.md).
- Signing credentials and the XMLDSig/XAdES-BES signature: see [signing](signing.md).
- ZIP archives and the preparation actions: see [packaging](packaging.md).
- Migrating replaced DTOs, exact values and renamed symbols: see [migration guide](migration.md).
- Service provider: see [src/EfaturaServiceProvider.php](../src/EfaturaServiceProvider.php).
- Document data objects: see [src/Data](../src/Data).

## Validation Scope

The package validates all nine fiscal document payloads and FDC/UDN event payloads through immutable Spatie Data values. The concrete document classes extend the abstract `DocumentData` contract; each owns its official fields. Receipts have no lines or totals, and transport documents have no totals. See [Fiscal domain validation](fiscal-domain.md) for entry points, required data, source precedence, and calculation policy.

Host applications remain responsible for persistence, middleware credentials, issued-document storage, and operational audit trails. Successful domain validation is not a completed, signed, authorized XML document: the XML actions write and validate the unsigned document, the preparation actions number, sign and package it, and transmission belongs to a later stage.
