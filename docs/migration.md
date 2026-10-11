# Migrating from the first release

This guide covers every public change between the first published `main`
(commit `580b0f0`) and this release. Each section names what existed, what
replaces it, and the steps to upgrade. Symbols that only existed while this
release was being built are not listed.

## Runtime

The package needs PHP 8.5 and Laravel 13, and adds `psr/clock`,
`illuminate/database` and `illuminate/http` (with Guzzle, already present in a
Laravel application) to its dependencies. Run `composer update akira/efatura`.
`php artisan efatura:install` skips a config file that already exists, so merge
the new keys by hand as described under [configuration](#configuration).

Document numbers are now reserved in a database table. Publish its migration
and run it, after setting `efatura.database.connection` and
`efatura.database.sequences_table` if the defaults do not fit:

```sh
php artisan vendor:publish --tag=efatura-migrations
php artisan migrate
```

`efatura:install` publishes the same migration, and skips it when a migration
named `*_create_efatura_sequences_table.php` already exists. The package never
runs it on its own. See [document numbers and sequences](sequences.md).

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

An extra field `name` follows the XML 1.0 name characters (NameStartChar and
NameChar, without the colon): `a²`, `a½` or `aª`, which the previous pattern
accepted and the XML writer then could not write, now fail at
`footer.extraFields.N.name`. A `namespace` must be an absolute URI without
whitespace or any of `"<>{}|\^` and the backtick, and the XML namespaces
`http://www.w3.org/2000/xmlns/` and `http://www.w3.org/XML/1998/namespace` are
refused like the official one, at `footer.extraFields.N.namespace`.
Percent-encode those characters in a namespace that carries them.

A percentage discount (`valueType` `P`) carries at most three decimal places
and at most 100, as the Manual's PERC type and the XSD's
`stDiscountPercentage` fix, and is written with three places. A value such as
`12.3456` used to validate and now fails at its `value` field with
`Value exceeds the allowed decimal precision.`; round it to three places before
building the document. Amount discounts (`A`) keep five places.

A contact `email` and a self-billing `authorizationId` follow the XSD's
`stEmail` and `stUUID` patterns, where `\w` is any character except
punctuation, separators and other characters, so the underscore is not a word
character. `_billing@example.cv`, `billing__team@example.cv` and an
`authorizationId` with an underscore inside a group used to validate and then
failed the schema after a number was reserved; they now fail at `email` or
`authorizationId` with Laravel's `regex` message. An underscore
still separates two words, as in `billing_team@example.cv`. Symbols such as `+`
are accepted.

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
| `new EfaturaManager($config)` | `new EfaturaManager($config, $clock, $transports)` with a `Transport\TransportResolver`; resolve it from the container instead |
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
| `efatura.certificates.ca_bundle_path` | New, from `EFATURA_CA_BUNDLE_PATH`: an optional PEM file of CA certificates on the certificates disk; when set, the signing certificate must chain to it (see [signing](signing.md#icp-cv-and-the-ca-bundle)) |
| `efatura.http.middleware.base_url` / `EFATURA_MIDDLEWARE_BASE_URL` | The default changes from `https://localhost:3443` to `https://localhost:3443/v1`. Base URLs now carry the API version on both profiles, as the platform default already did, and requests use resources relative to them (`dfe`, `event`). An application that set `https://localhost:3443` explicitly, in its config or its `.env`, appends `/v1` |
| `efatura.driver` / `EFATURA_DRIVER` | New: `middleware` (the default) or `platform`, in any letter case, or a `TransportDriver` case; anything else fails with `configuration.invalid_driver` |
| `efatura.transmitter.middleware_key` / `EFATURA_TRANSMITTER_KEY` | When set, it must have exactly 64 characters (Manual 11.4.1); any other length fails loading with `configuration.invalid_length`, and a space, a control character or anything outside printable ASCII with `configuration.invalid_format`; neither message contains the key |
| `efatura.transmitter.oauth.issuer_url` / `EFATURA_OAUTH_ISSUER_URL` | New: the OpenID issuer of the platform, `https://iam.efatura.cv/auth/realms/taxpayers` by default, with the same HTTPS rules as the base URLs |
| `efatura.http.ca_bundle_path` and `efatura.http.<client>.ca_bundle_path` / `EFATURA_HTTP_CA_BUNDLE_PATH`, `EFATURA_MIDDLEWARE_CA_BUNDLE_PATH` | New: an absolute path to a readable local PEM file the client trusts instead of the system store, inherited like the other client options. A relative path fails with `configuration.unsafe_path`, a missing file with `configuration.unreadable_path`, and a bundle on a client whose `verify_tls` is false with `configuration.conflicting_tls` |
| `efatura.cache` | The store may now hold platform access tokens, encrypted with the application key; refresh tokens, client secrets and the middleware key are never cached |
| `efatura.http.retries`, `retry_delay_milliseconds` and their per-client overrides | Now used, and only for reads: document lookups, OpenID discovery and remote readiness. Submissions, self-billing requests and token requests are never retried (see [transport](transport.md#retries)) |
| `efatura.database.connection`, `sequences_table` | Now used: the connection and table of the document number counters (see [document numbers and sequences](sequences.md)) |
| `efatura.exchange_rates.provider` / `EFATURA_EXCHANGE_RATE_PROVIDER` | New: `bcv` (the default), `world_bank`, `fixed` or `callback`, in any letter case; anything else fails with `configuration.invalid_exchange_rate_provider` (see [currency conversion](currency.md)) |
| `efatura.exchange_rates.bcv.rate_type`, `max_publication_age_days` / `EFATURA_BCV_RATE_TYPE`, `EFATURA_BCV_MAX_PUBLICATION_AGE_DAYS` | New: `buy` (the default) or `sell`, otherwise `configuration.invalid_rate_type`; and the age in days of an earlier BCV publication still accepted, 0 by default |
| `efatura.exchange_rates.world_bank.economies`, `observation_year` / `EFATURA_WORLD_BANK_OBSERVATION_YEAR` | New: a map of currency to World Bank economy code, and `previous` (the default) or `request`, otherwise `configuration.invalid_observation_year` |
| `efatura.exchange_rates.fixed.rates`, `rate_type`, `name` and `efatura.exchange_rates.callback` | New: the rates of the `fixed` provider, and the class the `callback` provider resolves |
| `efatura.http.bcv` / `EFATURA_BCV_BASE_URL` | Now used: the client profile that reads the BCV page. Its `base_url` alone may carry a query string, as the official page address does |
| `efatura.http.world_bank.base_url` / `EFATURA_WORLD_BANK_BASE_URL` | Removed. The World Bank endpoint is fixed (`https://api.worldbank.org`); a config whose key still holds a value (for example from `EFATURA_WORLD_BANK_BASE_URL`) fails loading with `configuration.fixed_endpoint`, and a null one is ignored. Delete the key and the variable. The other `efatura.http.world_bank` options are used |
| `efatura.cache.exchange_rates_ttl_seconds` / `EFATURA_EXCHANGE_RATES_TTL_SECONDS` | Now used: how long BCV and World Bank quotes stay cached, 3600 by default |
| `efatura.dfa.base_url` / `EFATURA_DFA_BASE_URL` | New: the viewer address the DFA QR code points at, `https://pe.efatura.cv/dfe/view` by default (see [DFA](dfa.md#qr-code)) |
| `efatura.dfa.cache_path` / `EFATURA_DFA_CACHE_PATH` | New: an absolute directory for the dompdf font cache, temporary files and chroot; null uses `storage/framework/cache/efatura-dompdf` of the application. A directory owned by another user or writable by its group or others fails with `dfa.cache_unavailable` when a DFA is rendered. A relative path or one with `..`, `.` or empty segments fails with `configuration.unsafe_path` |
| `efatura.storage.disk`, `path` | Now used, by `StoreIssuedArtifactsAction` only (see [artifact storage](storage.md)). A disk whose `visibility` is `public` is refused when an artifact is written |

`EmitterConfig::$led` changes from `?string` to `?int`. `AddressConfig` and
`ContactsConfig` gain the new fields as optional trailing constructor
arguments, so existing positional calls keep working. `CertificateConfig` gains
`caBundlePath` as an optional trailing constructor argument. `HttpClientConfig` gains `caBundlePath` and `verify()`,
`OAuthConfig` gains `issuerUrl` and `EfaturaConfig` gains `driver`,
`exchangeRates` (an `ExchangeRatesConfig`) and `dfa` (a `DfaConfig` with
`baseUrl` and `cachePath`), each as an optional trailing constructor argument; the JSON form of `OAuthConfig` now
includes `issuerUrl`. `CertificateConfig`,
`OAuthConfig` and `TransmitterConfig` redact their secrets from `dump()`,
`var_dump()` and `print_r()` output, and implement `JsonSerializable` with the
same redaction, so `json_encode()` and Monolog's normalizer write `[redacted]`.
`var_export()` and `serialize()` still write the secrets, because neither
consults `__debugInfo()` or `jsonSerialize()`; never pass these objects to them.

Merge the new keys from the published [config](../config/efatura.php) into an
application config file that was published before, then set the address code
and address detail the emitter needs.

Every HTTPS address the package checks now refuses a fragment (`#...`) as well
as credentials: the configured base URLs, the issuer and the DFA base URL, the
source and evidence URLs of an exchange rate quote (`exchange_rate.source_required`)
and the token endpoint the OpenID discovery returns. Only the BCV base URL, the
quote and evidence URLs and the token endpoint may carry a query string.

## Sending documents

The first release could validate a document but not send it. Sending is new and
replaces nothing, so no code has to change: `IssueDocumentAction` and
`IssueEventAction` prepare and submit, `ReconcileDocumentAction` resolves an
unknown outcome, `RequestSelfBillingAuthorizationAction` asks for a self-billing
authorization, and `CheckReadinessAction` checks the installation (see
[issuance](issuance.md), [transport](transport.md) and [readiness](readiness.md)).
To use the middleware, set `EFATURA_TRANSMITTER_KEY` and, for its self-signed
certificate, `EFATURA_MIDDLEWARE_CA_BUNDLE_PATH`. To send directly to the
platform, set `EFATURA_DRIVER=platform`, `EFATURA_EMITTER_TAX_ID`, the OAuth client
of the transmitter and bind a `Contracts\RefreshTokenRepository`. Tests replace the
driver with `Testing\FakeTransport`. An application that records outgoing HTTP
requests (Telescope, Nightwatch) hides the `authorization` and
`cv-ef-mw-core-transmitter-key` headers.

## Exchange rates, DFA and storage

Converting a document to CVE, printing the DFA and keeping the artifacts of an
issued document are new and replace nothing, so no code has to change; each is
opt-in. See [currency conversion](currency.md), [DFA](dfa.md) and
[artifact storage](storage.md).

- `ConvertDocumentCurrencyAction` reads a quote from the bound
  `Contracts\ExchangeRateProvider` and writes `PayableAlternativeAmount`.
- `BuildDfaDataAction` and `Contracts\DfaRenderer` print the DFA. They need
  `dompdf/dompdf` and `bacon/bacon-qr-code` `^3.1`, which the package suggests
  and does not require; Composer refuses older versions of both. Publish the
  views to change the layout with
  `php artisan vendor:publish --tag=efatura-views`.
- `StoreIssuedArtifactsAction` writes through `Contracts\ArtifactStore` and
  stores only an authorized document.

The new error codes are `exchange_rate.*` (`ExchangeRateException`, listed in
[currency conversion](currency.md#errors)), `dfa.*` (`DfaException`:
`dfa.not_authorized`, `dfa.invalid_iud`, `dfa.iuc_required`,
`dfa.invalid_iuc`, `dfa.iuc_mismatch`, `dfa.renderer_unavailable`,
`dfa.cache_unavailable`), `storage.*` (`StorageException`:
`storage.not_authorized`, `storage.dfa_mismatch`, `storage.invalid_iud`,
`storage.disk_invalid`, `storage.public_disk`, `storage.conflict`,
`storage.missing`, `storage.corrupted`, `storage.unavailable`) and, when the
configuration loads, `configuration.invalid_exchange_rate_provider`,
`configuration.invalid_rate_type`, `configuration.invalid_observation_year`
and `configuration.fixed_endpoint`.

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
`install.*` keys the command printed before are unchanged except
`install.optional_packages_notice`, which now reads "Optional DFA packages not
detected: :packages. Install them to render the Documento Fiscal Auxiliar as a
PDF with its QR code."; update an override of it. The command adds
`install.migration_published` and `install.migration_exists`. The new `validation.*` keys
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
  transmit or issue a document. `PrepareDocumentAction` and
  `PrepareEventAction` number, sign and package a document or event without
  sending it (see [packaging](packaging.md#preparation)); a number they reserve
  stays consumed when a later step fails.
