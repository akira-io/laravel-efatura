# Migrating fiscal payloads

Replace the old generic invoice and `{invoice: ...}` wrapper with a canonical
concrete document such as `ElectronicInvoiceData`, or use `Efatura::invoice()`
with `DocumentType::Invoice`. `InvoiceData` is now an abstract contract. The nine
document classes own their official fields; receipts have no lines or totals,
and transport documents have no totals. See the [field graph](fiscal-domain.md).

Replace `nif` with `taxId: {value: '100200300', countryCode: 'CV'}`. Compose
`DocumentHeaderData` with issue date, issue time and LED, plus supplied allocation
fields when available. Item descriptions belong to `ItemData`; quantities use
`QuantityData`; prices and totals use the named official monetary fields shown
in the executable [Quick Start](../README.md#quick-start).

Use exact decimal strings, `Brick\Math\BigDecimal` and supported `Brick\Money\Money`
values instead of floats. Fiscal money is CVE with five decimal places; quantity,
percentage and alternative currency fields retain their own contracts. Do not
apply floating-point calculations before passing values into Data.

Published emitter configuration now supports `address_detail`, `address_code`,
state, street detail, building fields, telefax and website. Merge these keys into
an existing application config using the [configuration reference](builders.md).
Provide a complete CV address and contacts and replace nonnumeric LED values
with the registered integer LED. Partial configuration can boot, but builders
validate the chosen profile. Explicit emitter replacement clears the prior LED;
pass it in the same call or set it afterward.

Constructors and Spatie factories validate immediately. Builders defer draft
validation to `validate()`, but the typed Data passed into setters is already
validated when constructed. A builder defaults issue date/time from the package
clock, while direct DTO construction requires them explicitly. Successful
validation yields staged fiscal data: sequence/IUD allocation, completed XML
envelope preparation, signing, transmission and authority acceptance are later
operations. There is no `issue()` operation in this API.
