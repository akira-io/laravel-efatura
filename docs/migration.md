# Migrating from the first release

This guide covers every public change between the first published `main`
(commit `580b0f0`) and this release. Each section names what existed, what
replaces it, and the steps to upgrade. Symbols that only existed while this
release was being built are not listed.

## Runtime

The package needs PHP 8.5 and Laravel 13, and adds `psr/clock` to its
dependencies. Run `composer update akira/efatura`. `php artisan
efatura:install` skips a config file that already exists, so merge the new
keys by hand as described under [configuration](#configuration).

## Documents

The first release modelled every document as an `InvoiceData` wrapped in a
type-specific class: `{invoice: {type, issueDate, emitter, receiver, lines,
totals, originalIud, creditNoteReason}}`. Each document is now one concrete
class carrying the official fields of Manual 11 at the top level, with no
`invoice` wrapper and no `type` field.

| Before | After |
| --- | --- |
| `Data\InvoiceData` (final, concrete) | `Data\DocumentData` (abstract); build the concrete class |
| `ElectronicInvoiceData`, `ReceiptInvoiceData`, `SalesReceiptData`, `CreditNoteData`, `TransportDocumentData` holding `$invoice` | The same classes, with `header`, `emitter`, `receiver`, `lines`, `totals` and their own fields |
| No class for RCE, NDE, DVE and NLE | `ReceiptData`, `DebitNoteData`, `ReturnNoteData`, `RegistrationNoteData` |
| `<Document>Data::TYPE` | `<Document>Data::documentType()` and `$document->type()` |
| `invoice.type` in the payload | Implied by the class; `DocumentType::dataClass()` names the class for a type |
| `invoice.issueDate` | `header.issueDate` plus `header.issueTime` and `header.ledCode` |
| `invoice.originalIud` | `references[].fiscalDocument` |
| `invoice.creditNoteReason` | `issueReasonCode` (an `IssueReason` value) and `issueReasonDescription` |

Upgrade steps:

1. Remove the `invoice` key and move its fields to the top level of the
   payload given to the concrete class, or build the document with
   `Efatura::invoice()` (see the [Quick Start](../README.md#quick-start) and
   [builders and configuration](builders.md)).
2. Replace `type` with the class: `DocumentType::Invoice->dataClass()` returns
   `ElectronicInvoiceData::class`.
3. Move `issueDate` into `header` and add `issueTime` and `ledCode`.
4. On credit notes, send the original document in `references` and the reason
   as `issueReasonCode`.

The [field graph](fiscal-domain.md) lists the sections each document accepts:
receipts have no lines or totals, and transport documents have no totals.

### Parties

| Before (`PartyData`) | After |
| --- | --- |
| `string $nif` | `?TaxIdData $taxId`, input `taxId: {value: '100200300', countryCode: 'CV'}` |
| `string $name` | `?string $name` |
| `?string $address`, `?string $city`, `?string $country` | `?AddressData $address` with `countryCode`, `addressDetail`, `addressCode`, `city` and the other official address fields |
| none | `?ContactsData $contacts`, `?PartyReference $reference` |

An emitter needs a complete Cabo Verde address, with an official
`addressCode`, and contacts. A NIF is `[1-9][0-9]{8}` in Cabo Verde.

### Lines and taxes

| Before (`LineItemData`) | After |
| --- | --- |
| `string $description` | `ItemData $item` with `description` and `emitterIdentification` |
| `float $quantity` | `QuantityData $quantity` with `value` (decimal string) and `unitCode` |
| `float $unitPrice` | `?Money $price` |
| `float $total` | `?Money $priceExtension` and `?Money $netTotal` |
| none | `lineTypeCode`, `id`, `lineReferenceId`, `orderLineReference`, `discount` |

| Before (`TaxData`) | After |
| --- | --- |
| `string $type` | `TaxType $taxType`, input and output key `taxTypeCode` |
| `float $rate` | `?BigDecimal $taxPercentage` |
| `float $amount` | `?Money $taxAmount` (fixed amounts) and `?Money $taxTotal` (line evidence) |
| `?string $exemptionReason` | `?string $taxExemptionReasonCode`, from the official catalog |
| none | `?StampTaxCode $stampTaxCode` for `IS` |

### Totals

| Before (`TotalsData`) | After |
| --- | --- |
| `float $subtotal` | `Money $priceExtensionTotalAmount` and `Money $netTotalAmount` |
| `float $taxTotal` | `Money $taxTotalAmount` |
| `float $grandTotal` | `Money $payableAmount` |
| none | `chargeTotalAmount`, `discountTotalAmount`, `withholdingTaxTotalAmount`, `payableRoundingAmount`, `discount`, `payableAlternativeAmounts` |

`payableAmount` must equal `netTotalAmount + taxTotalAmount -
withholdingTaxTotalAmount + payableRoundingAmount`, and the lines must
reconcile with the totals.

### Numbers

Floats are rejected everywhere they used to be accepted. Pass decimal strings,
integers, `Brick\Math\BigDecimal` or `Brick\Money\Money`. CVE amounts keep five
decimal places, percentages three, and every decimal at most 15 integer digits.
`FiscalMoney::cve()` and `FiscalMoney::of()` build exact amounts;
`FiscalMoney::rounded()` rounds half up to two places.

### Validation

`from()` and `validateAndCreate()` validate the whole graph in one pass and
report every failure at its full path, such as `lines.2.taxes.0.taxTypeCode`.
The first release stopped at the first failure and reported under `invoice.*`.
Error messages are Laravel's defaults plus the package keys listed under
[translations](#translations). Code that matched the old messages, such as
"Emitter is required.", must match the field path instead.

All nine document types validate and build. The first release refused NDE,
RCE, DVE and NLE through its document type policy.

A document accepts at most 1000 `lines` and 1000 `references`, and the
extension lists at most 100 entries each; Manual 11 sets no count, so these
are defensive limits, listed in [fiscal domain validation](fiscal-domain.md).

A received IUD, in an event's `iuds` or in `references[].fiscalDocument.value`,
must carry the Luhn check digit of Manual 11. An identifier with the official
shape and a wrong last digit now fails at that field with
`validation.iud_invalid`; the authority refuses it anyway. Old document
references such as `1/2021/A/1` are unaffected. Replace stored or fixture IUDs
built by padding with zeros by real identifiers, or compute the last digit with
`Akira\Efatura\Support\Luhn::checkDigit()` over the 42 digits between `CV` and the check digit.

## Document types and environments

| Before | After |
| --- | --- |
| `DocumentType::ELECTRONIC_INVOICE` | `DocumentType::Invoice` |
| `DocumentType::ELECTRONIC_INVOICE_RECEIPT` | `DocumentType::InvoiceReceipt` |
| `DocumentType::ELECTRONIC_SALES_TICKET` | `DocumentType::SalesReceipt` |
| `DocumentType::ELECTRONIC_RECEIPT` | `DocumentType::Receipt` |
| `DocumentType::ELECTRONIC_CREDIT_NOTE` | `DocumentType::CreditNote` |
| `DocumentType::ELECTRONIC_DEBIT_NOTE` | `DocumentType::DebitNote` |
| `DocumentType::ELECTRONIC_TRANSPORT_DOCUMENT` | `DocumentType::Transport` |
| `DocumentType::ELECTRONIC_RETURN_NOTE` | `DocumentType::ReturnNote` |
| `DocumentType::ELECTRONIC_ENTRY_NOTE` | `DocumentType::RegistrationNote` |
| `Environment::PRODUCTION`, `::HOMOLOGATION`, `::TEST` | `Environment::Production`, `::Homologation`, `::Test` |

The case values (`'FTE'`, `1`, ...) are unchanged, so stored values and
`DocumentType::from('FTE')` keep working; replace the case names in code.
`Environment::fromName()` now ignores letter case, so `TEST`, `test` and
`Test` all select `Environment::Test`.

## Removed symbols

| Removed | Replacement |
| --- | --- |
| `Contracts\DocumentTypePolicy` and its container binding | None. Every document type is supported; remove custom policy bindings |
| `Support\DefaultDocumentTypePolicy` | None |
| `Concerns\ValidatesInvoiceType` | None. The concrete class decides the type |
| `Data\InvoiceData` | `Data\DocumentData` and the nine concrete classes |
| `<Document>Data::TYPE` constants | `<Document>Data::documentType()` |
| `new EfaturaValidationException($field, $message)` | Thrown by the package only; read `$exception->field()` and `$exception->errorCode` |

`EfaturaValidationException` now carries a specific `errorCode`
(`decimal.invalid`, `decimal.scale_exceeded`, `decimal.integer_digits_exceeded`,
`money.invalid_currency`, `money.currency_mismatch`, `money.invalid`) instead
of `validation.invalid_value`. It is raised by the `FiscalMoney` and
`DecimalFormatter` API; Data validation raises Laravel's `ValidationException`.
Programming errors, such as a negative scale or a class that is not a
document, raise `Exceptions\DefinitionException`, which extends
`EfaturaException`.

## Entry points

| Before | After |
| --- | --- |
| `new Efatura($config)` | `new Efatura($config, $clock)` with a `Psr\Clock\ClockInterface`; resolve it from the container instead |
| `new EfaturaManager($config)` | `new EfaturaManager($config, $clock)`; resolve it from the container instead |
| `Efatura::config()`, `efatura()`, `withConfig()` | Unchanged |
| none | `Efatura::invoice()` and `Efatura::event()` return builders |

The service provider now binds `Psr\Clock\ClockInterface` to a Carbon factory
in `Atlantic/Cape_Verde` and the `Support\Catalogs` singleton. An application
that binds its own `ClockInterface` after the provider registers keeps it.

## Configuration

| Key | Change |
| --- | --- |
| `efatura.environment` / `EFATURA_ENVIRONMENT` | Unchanged: a name in any letter case or the codes `1`, `2`, `3`, defaulting to the test environment; the published file now spells the default `test` instead of `TEST` |
| `efatura.emitter.tax_id`, `efatura.transmitter.tax_id` | Must match `[1-9][0-9]{8}`; a NIF starting with 0 now fails loading |
| `efatura.emitter.led` | An integer or a string matching `[1-9][0-9]{0,4}`; anything else fails with `configuration.invalid_led` |
| `efatura.emitter.address.address_detail`, `address_code`, `state`, `street_detail`, `building_name`, `building_number`, `building_floor` | New, from `EFATURA_EMITTER_ADDRESS_DETAIL`, `EFATURA_EMITTER_ADDRESS_CODE`, `EFATURA_EMITTER_STATE`, `EFATURA_EMITTER_STREET_DETAIL`, `EFATURA_EMITTER_BUILDING_NAME`, `EFATURA_EMITTER_BUILDING_NUMBER`, `EFATURA_EMITTER_BUILDING_FLOOR` |
| `efatura.emitter.contacts.telefax`, `website` | New, from `EFATURA_EMITTER_TELEFAX` and `EFATURA_EMITTER_WEBSITE` |

`EmitterConfig::$led` changes from `?string` to `?int`. `AddressConfig` and
`ContactsConfig` gain the new fields as optional trailing constructor
arguments, so existing positional calls keep working. `CertificateConfig`,
`OAuthConfig` and `TransmitterConfig` redact their secrets from `dump()`,
`var_dump()` and `print_r()` output, and implement `JsonSerializable` with the
same redaction, so `json_encode()` and Monolog's normalizer write `[redacted]`.
`var_export()` and `serialize()` still write the secrets, because neither
consults `__debugInfo()` or `jsonSerialize()`; never pass these objects to them.

Merge the new keys from the published [config](../config/efatura.php) into an
application config file that was published before, then set the address code
and address detail the emitter needs.

## Translations

The package translations are now read from the `efatura` namespace. The first
release looked them up as `efatura.*` in the application's own
`lang/{locale}/efatura.php`; move any overrides to
`lang/vendor/efatura/{locale}/efatura.php`.

These keys are gone, because nothing produces those messages any more:
`validation.invoice_type_mismatch`, `emitter_nif_required`,
`emitter_name_required`, `party_nif_required`, `party_name_required`,
`receiver_nif_required`, `receiver_name_required`, `receiver_required`,
`emitter_required`, `totals_required`, `invoice_required`, `lines_required`,
`totals_negative`, `na_tax_exemption_required`; `invoice.issue_date_required`,
`receiver_required_for_type`, `original_iud_required`,
`credit_note_reason_required`, `document_type_not_supported`;
`config.transmitter_nif_required`, `transmitter_led_required`,
`software_code_required`, `software_name_required`,
`software_version_required`, `middleware_base_url_required`,
`environment_invalid`; `install.command_description`; `general.package`. The
`install.*` keys the command prints are unchanged. The new `validation.*` keys
are listed in [resources/lang/en/efatura.php](../resources/lang/en/efatura.php).

## Renamed symbols

PHP names describe the domain concept; wire names (input keys, `toArray()`
output, validation error keys and XML elements) keep the official names
through `#[MapName]`. These are the properties whose PHP name differs from the
wire name:

| Property | Wire key |
| --- | --- |
| `DocumentHeaderData::$series`, `EventNumberRangeData::$series` | `serie` |
| `LineItemData::$lineType` | `lineTypeCode` |
| `TaxData::$taxType` | `taxTypeCode` |
| `ReceiptData::$receiptType` | `receiptTypeCode` |
| `CreditNoteData`, `DebitNoteData`, `ReturnNoteData` `::$issueReason` | `issueReasonCode` |
| `TransportDocumentData::$transportDocumentType`, `::$receiverType` | `transportDocumentTypeCode`, `receiverTypeCode` |
| `TransportLocationData::$transportMode` | `transportModeCode` |
| `ContingencyData::$reason` | `reasonTypeCode` |
| `EventData::$eventType` | `eventTypeCode` |
| `EventNumberRangeData::$documentType` | `documentTypeCode` |
| `RentReceiptData::$rentPurpose`, `::$contractType`, `::$rentType` | `rentPurposeTypeCode`, `contractTypeCode`, `rentTypeCode` |

Properties that hold a code from an official table, such as `ledCode`,
`addressCode`, `countryCode`, `unitCode` or `TaxData::$stampTaxCode`, keep
the official name in PHP too.

## Behaviour to know

- Issuance instants (`header.issueDate` and `issueTime`, the contingency
  issue date and time, `EventData::$issueDateTime`, `issuedAt()` and the
  builder clock) are converted to `Atlantic/Cape_Verde` before formatting, so
  `00:30 UTC` on 3 October is issued on 2 October at `23:30:00`. Calendar
  fields keep the date as given.
- The emission window (online: 24 hours before to one hour after the clock;
  contingency: seven days before, with no future bound) is checked only by
  `DocumentBuilder::build()`, so a stored document can be rehydrated with
  `from()` whatever its age.
- Building Data with `new` does not validate; use `from()` for untrusted input.
- Validation, building and verification never allocate numbers or IUDs, sign,
  transmit or issue a document.
