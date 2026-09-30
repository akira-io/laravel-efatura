# Laravel Efatura Documentation

`akira/efatura` is a Laravel package for Cabo Verde e-Fatura fiscal validation, typed document data, configuration, and middleware-oriented integration.

## Guides

- Installation and configuration: see the root [README.md](../README.md).
- Runtime configuration: see [config/efatura.php](../config/efatura.php).
- Service provider: see [src/EfaturaServiceProvider.php](../src/EfaturaServiceProvider.php).
- Document data objects: see [src/Data](../src/Data).

## Validation Scope

The package currently focuses on typed fiscal data, document type policy, Laravel configuration, and validation rules. Host applications remain responsible for persistence, middleware credentials, issued-document storage, and operational audit trails.
