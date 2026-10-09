# Migrating fiscal payloads

Replace the old generic invoice and `{invoice: ...}` wrapper with a canonical
concrete document such as `ElectronicInvoiceData`, or use `Efatura::invoice()`
with `DocumentType::Invoice`. `InvoiceData` is now the abstract `DocumentData` contract. The nine
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
validate the chosen profile. `emitter()` without a LED keeps the configured LED or
the one set by `ledCode()`, whatever the call order; pass a LED to replace it.

`from()` and `validateAndCreate()` validate the whole graph once, through
Spatie's validation pipeline, and report each failure at its full path, such as
`lines.2.taxes.0.taxTypeCode` or `emitter.contacts.email`. Direct construction
with `new` no longer validates: build Data from arrays with `from()` when the
input is untrusted. `PartyData::validateEmitter()` and
`ContactsData::validateEmitter()` are gone; the emitter rules now belong to the
document and report under `emitter.*`. `OfficialCode` takes the `Catalogs`
service as its second argument. Builders defer draft validation to `build()`,
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
through `DocumentBuilder::build()`. `from()` and `validateAndCreate()` no
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

Issuance Carbon values are instants. `DocumentBuilder::issuedAt()`,
`EventBuilder::issuedAt()`, the builder clock, `DocumentHeaderData::$issueDate`
and `$issueTime`, `ContingencyData::$issueDate` and `$issueTime`, and
`EventData::$issueDateTime` convert any `CarbonInterface`
to `Atlantic/Cape_Verde` before formatting, so a UTC `00:30` on 3 October is
issued on 2 October at `23:30:00`. Code that built those values from a host
clock and relied on the host's wall-clock fields now gets Cabo Verde time.
Calendar fields keep the date as given.

`EmitterConfig::$led` is now `?int`. The loader accepts only `EFATURA_EMITTER_LED`
values matching `[1-9][0-9]{0,4}` and fails with `configuration.invalid_led`
otherwise, instead of letting `1e2` become 100 or `abc` fail later with a type
error. Configured emitter and transmitter tax IDs follow the document pattern
`[1-9][0-9]{8}`, so a NIF starting with 0 now fails loading.

`DocumentType::dataClass()` names the concrete Data class of each document
type, and `DocumentType::fromDataClass()` resolves the reverse. `DocumentData`
derives `type()` and the static `documentType()` from that mapping, so both
are final: a document class outside the nine official ones has no type.

`Builders\ConfiguredEmitter` is gone. The configuration maps itself onto the
fiscal payload field by field: `EmitterConfig::partyPayload()` and
`EmitterConfig::taxIdPayload()`, built from `AddressConfig::addressPayload()`
and `ContactsConfig::contactsPayload()` (where `mobile` becomes
`mobilephone`). They return input arrays, not Data, so a partial profile still
reaches document validation and fails at its `emitter.*` path.

Builders keep the Data, enums and dates passed to their setters and assemble
the document once, in `build()`. `Builders\Concerns\HasDocumentSections`
is folded into `DocumentBuilder`, and every builder setter returns `static`.
Data given to `from()`, `validateAndCreate()` or a builder contributes its
values: `only()`, `except()`, `include()` and `exclude()` on that Data no longer
remove fields from the input, and `FiscalData::toPayload()` returns the same
values as an array.

The terminal builder method is now `build()`: `DocumentBuilder::validate()` and
`EventBuilder::validate()` are renamed, with the same return types and the
same validation. `DocumentBuilder::build()` still checks the emission window
against the builder's clock.

Catalog records use English keys: location rows returned by
`Catalogs::find(Catalog::Locations, ...)` and `Catalogs::records()` carry `code`,
`level`, `country`, `island`, `municipality`, `parish`, `zone`, `place` and
`name` instead of `codigo`, `nivel`, `pais`, `ilha`, `concelho`, `freguesia`,
`zona`, `lugar` and `nome`. `Catalog::codeField()` is gone, since every record
keys its code as `code`, and so is `Catalogs::sources()`: the checksums of the
official downloads live in `resources/official-artifacts.json`. The accepted
codes are unchanged.

`VerifyDocumentTotalsAction` subtracts the withholding aggregate from the
payable amount: `payableAmount` must equal `netTotalAmount + taxTotalAmount -
withholdingTaxTotalAmount + payableRoundingAmount`. A document that declared
IR withholding and a payable of net plus tax now fails at
`totals.payableAmount`; lower its payable by the withholding.

A sales receipt requires its `receiver` when `netTotalAmount + taxTotalAmount`
reaches 20000 CVE, instead of `payableAmount`. A receipt whose payable reaches
the threshold only through `payableRoundingAmount` may stay anonymous, and one
whose net plus tax reaches it must name the receiver even when withholding
lowers the payable below 20000.

Currencies follow the 178 uppercase codes of the XSD enumeration, in
`PayableAlternativeAmountData` and in `FiscalMoney` alike. The currency catalog
no longer lists the schema's literal `IdR`, so `IdR` and `IDR` are both
rejected. `FiscalMoney` and `CatalogCurrency::of()` resolve string currencies
through that catalog: a code outside it, such as `IDR`, `VED` or `ZZZ`, fails
with `money.invalid_currency` ("Currency must be an uppercase code of the
official currency catalog."), and catalog codes Brick does not ship, such as
`XDR`, now build fiscal money instead of failing with `money.invalid`.

## Renamed symbols

PHP names describe the domain concept; wire names (input keys, `toArray()`
output, validation error keys and XML elements) are unchanged unless the table
says otherwise. Properties that hold a code from an official table, such as
`ledCode`, `addressCode`, `countryCode`, `unitCode` or `TaxData::$stampTaxCode`,
keep their names.

`Environment::fromName()` and `EFATURA_ENVIRONMENT` accept a name in any letter
case, so `test`, `TEST` and `Test` all select `Environment::Test`; the codes
`1`, `2` and `3` still work. The published config defaults to `test`.

| Before | After |
| --- | --- |
| `Data\InvoiceData` | `Data\DocumentData` |
| `Builders\InvoiceBuilder` | `Builders\DocumentBuilder` (`Efatura::invoice()` keeps its name) |
| `DocumentHeaderData::$serie` | `DocumentHeaderData::$series` (input and output key `serie`) |
| `EventNumberRangeData::$serie` | `EventNumberRangeData::$series` (input and output key `serie`) |
| `LineItemData::$lineTypeCode` | `LineItemData::$lineType` (key `lineTypeCode`) |
| `TaxData::$taxTypeCode` | `TaxData::$taxType` (key `taxTypeCode`) |
| `ReceiptData::$receiptTypeCode` | `ReceiptData::$receiptType` (key `receiptTypeCode`) |
| `CreditNoteData`, `DebitNoteData`, `ReturnNoteData` `::$issueReasonCode` | `::$issueReason` (key `issueReasonCode`) |
| `TransportDocumentData::$transportDocumentTypeCode` | `TransportDocumentData::$transportDocumentType` (key `transportDocumentTypeCode`) |
| `TransportDocumentData::$receiverTypeCode` | `TransportDocumentData::$receiverType` (key `receiverTypeCode`) |
| `TransportLocationData::$transportModeCode` | `TransportLocationData::$transportMode` (key `transportModeCode`) |
| `ContingencyData::$reasonTypeCode` | `ContingencyData::$reason` (key `reasonTypeCode`) |
| `EventData::$eventTypeCode` | `EventData::$eventType` (key `eventTypeCode`) |
| `EventNumberRangeData::$documentTypeCode` | `EventNumberRangeData::$documentType` (key `documentTypeCode`) |
| `RentReceiptData::$rentPurposeTypeCode` | `RentReceiptData::$rentPurpose` (key `rentPurposeTypeCode`) |
| `RentReceiptData::$contractTypeCode` | `RentReceiptData::$contractType` (key `contractTypeCode`) |
| `RentReceiptData::$rentTypeCode` | `RentReceiptData::$rentType` (key `rentTypeCode`) |
| `Environment::PRODUCTION`, `::HOMOLOGATION`, `::TEST` | `Environment::Production`, `::Homologation`, `::Test` |
| `Money\MoneyCast`, `Money\BigDecimalCast`, `Money\ForeignMoneyCast`, `Money\DiscountValueCast` | `Casts\MoneyCast`, `Casts\BigDecimalCast`, `Casts\ForeignMoneyCast`, `Casts\DiscountValueCast` |
| `Money\MoneyTransformer`, `Money\BigDecimalTransformer`, `Money\DiscountValueTransformer` | `Transformers\MoneyTransformer`, `Transformers\BigDecimalTransformer`, `Transformers\DiscountValueTransformer` |

Laravel Data casts live in `Akira\Efatura\Casts` and transformers in
`Akira\Efatura\Transformers`. `Akira\Efatura\Money` keeps the money value
objects and services: `FiscalMoney`, `DecimalFormatter`, `CatalogCurrency` and
`TotalsAccumulator`.

`efatura:install` takes its description from the `#[Description]` attribute.
The `install.command_description` translation key is gone, along with the
validation, invoice, config and general keys that nothing in the package used.
