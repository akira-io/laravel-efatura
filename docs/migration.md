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

`from()` and `validateAndCreate()` validate the whole graph once, through
Spatie's validation pipeline, and report each failure at its full path, such as
`lines.2.taxes.0.taxTypeCode` or `emitter.contacts.email`. Direct construction
with `new` no longer validates: build Data from arrays with `from()` when the
input is untrusted. `PartyData::validateEmitter()` and
`ContactsData::validateEmitter()` are gone; the emitter rules now belong to the
document and report under `emitter.*`. `OfficialCode` takes the `Catalogs`
service as its second argument. Builders defer draft validation to `validate()`,
which validates the assembled document, including Data passed into setters. A builder defaults issue date/time from the package
clock, while direct DTO construction requires them explicitly. Successful
validation yields staged fiscal data: sequence/IUD allocation, completed XML
envelope preparation, signing, transmission and authority acceptance are later
operations. There is no `issue()` operation in this API.

`ReconcileDocumentTotalsAction` is now `VerifyDocumentTotalsAction`. Its
`handle()` returns `void` instead of echoing the `TotalsData` it received.
`DecimalFormatter::roundingMode(bool)` is replaced by
`DecimalFormatter::fiscalRounding()`, which names the half-up fiscal rounding.

The emission window (online: 24 hours before to one hour after the clock;
contingency: seven days before) is checked only when a document is issued
through `InvoiceBuilder::validate()`. `from()` and `validateAndCreate()` no
longer reject a document because of its age, so an issued document can be
rehydrated from storage. Code that relied on `from()` to enforce the window
must issue through the builder or call `ValidateIssueDateAction` itself.

Document compatibility rules now run in the same validation pass as the rest
of the payload and report at full paths: duplicate line identifiers under
`lines.N.id`, missing line prices or taxes under `lines.N.price` and
`lines.N.taxes`, settled-payment conflicts under `payments.payments` and
`payments.paymentDueDate`, and an invoice receipt payment date under
`payments.payments.N.paymentDate`. The previous bare keys (`ids.N`, `price`,
`payments`, `paymentDate`, `receiverReference`) are gone. Allowed issue
reasons per document come from `IssueReason::allowedFor(DocumentType)`.
