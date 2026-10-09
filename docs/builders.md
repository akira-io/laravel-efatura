# Builders and emitter configuration

`Efatura::invoice()` returns a new mutable `DocumentBuilder`, defaulting to
`DocumentType::Invoice`. `Efatura::event()` returns a new `EventBuilder`; select
its event type explicitly. `invoice()`, `event()` and `config()` exist on the
facade, on `EfaturaManager` and on the `Efatura` instance that
`EfaturaManager::efatura()` returns; `efatura()` and `withConfig()` exist only
on the facade and the manager. Each `build()` returns a fresh canonical
Data graph with readonly fiscal fields. It never allocates a number or IUD,
signs, submits, or issues a document.

Invoice setters are `type`, `emitter`, `ledCode`, `header`, `issuedAt`, `receiver`,
`line`, `totals`, `reference`, `emission`, and `footer`. Header and footer Data
expose every official common field, including series, supplied document number,
self-billing authorization, note, and extra fields. `line` and `reference` append;
other setters replace their entire value. The builder keeps the Data, enums and
dates you pass and assembles the document from them in `build()`, so the
whole graph is validated once, at its full paths. Data contributes its values:
presentation partials such as `only()` or `except()` on the source Data, before
or after it is set, do not change the document. Dates are kept as immutable
copies.

Document-specific setters accept their canonical Data, enum, date or scalar:

| Fields | Setters |
| --- | --- |
| Invoice dates and order | `dueDate`, `taxPointDate`, `orderReference` |
| Payment and delivery | `payments`, `paymentParty`, `delivery` |
| Notes | `issueReason`, `issueReasonDescription`, `rappelPeriod` |
| Receipts | `receiptType`, `rentReceipt` |
| Transport | `receiverType`, `transportDocumentType`, `transportServiceProvider`, `transportRoute` |

Select any of the nine `DocumentType` cases; validation returns the corresponding
concrete class listed in [fiscal domain validation](fiscal-domain.md). Unsupported
sections are rejected, including totals on transport documents and lines on
receipts. Changing `type()` retains your draft so incompatible fields produce
validation errors instead of silently disappearing. Start a fresh builder when
switching to a different document shape. Optional receivers can simply be omitted
on the types that permit them.

Events expose `type(EventType)`, `emitter(TaxIdData)`, `issuedAt(CarbonInterface)`,
`reason(string)`, `iud(string)` (append), `numberRange(EventNumberRangeData)`, and
`emission(EmissionContextData)`. FDC requires IUDs; UDN requires a number range.
Their conflicting target sections fail validation.

Both builders snapshot the injected PSR-20 `ClockInterface` at creation. The default clock uses
Atlantic/Cape_Verde. `issuedAt()` takes any `CarbonInterface` as an instant and
writes its Cabo Verde date and time, so `00:30 UTC` on 3 October becomes issue
date 2 October at `23:30:00`. `dueDate()` and `taxPointDate()` take a calendar
date: they write the Carbon's own `Y-m-d`, with no timezone shift. `header()` replaces
all header fields, including LED and dates. No numbering fields are generated.
`DocumentBuilder::build()` also checks the emission window against the same
clock and reports `header.issueDate` when the issue date and time fall outside it.
Transmission software and transmitter configuration remain separate: only an
explicit `emission()` sets transmission context at this stage. Credentials never
enter the fiscal Data graph.

## Configured emitter

The published [Laravel config](../config/efatura.php) documents infrastructure,
host inheritance, secrets, HTTP defaults and all environment keys. Emitter
values below are nullable strings, default to `null`, and are normalized at
configuration load. Invalid non-string or blank supplied values fail loading, and
so do a tax ID or LED that does not match its fiscal pattern; complete fiscal
validation happens when validating the selected document.

| Config key below `efatura.emitter` | Typed config property / Data field |
| --- | --- |
| `tax_id`, `name`, `led` | `taxId`, `name`, `led` / `taxId.value`, `name`, `header.ledCode` |
| `address.country_code`, `address.address_detail`, `address.address_code` | `countryCode`, `addressDetail`, `addressCode` |
| `address.state`, `address.region`, `address.city`, `address.street` | Same camelCase names |
| `address.street_detail`, `address.building_name`, `address.building_number`, `address.building_floor`, `address.postal_code` | `streetDetail`, `buildingName`, `buildingNumber`, `buildingFloor`, `postalCode` |
| `contacts.email`, `contacts.telephone`, `contacts.mobile` | `email`, `telephone`, `mobile` / `mobilephone` |
| `contacts.telefax`, `contacts.website` | `telefax`, `website` |

A complete CV emitter supplies a CV tax ID (nine digits, the first from 1 to 9), name, country `CV`, an
address detail, an official address code, email, telephone or mobile, and LED.
LED must be an integer or a decimal integer string in `1..99999` without sign,
exponent, fraction or leading zero (`FiscalRules::LED`); surrounding whitespace is
trimmed like every config string. Anything else fails loading with
`configuration.invalid_led`, and `EmitterConfig::$led` holds the converted integer.
Configured tax IDs share `FiscalRules::CV_TAX_ID` with document validation. Address codes must occur in the bundled location catalog. Additional
address and contact fields are optional; no values are fabricated.

An all-null emitter or `emitter => null` provides no default. A partial profile
can be loaded and replaced before validation. `emitter($party, ledCode: 22)`
replaces the entire issuer profile. It never merges address or contact fields.
Calling `emitter($party)` without a LED keeps the configured LED or the one set
by `ledCode()`, in either order; when the new issuer uses another LED, pass it
to `emitter()` or call `ledCode()`. Missing or invalid selected values fail
with Laravel validation errors. Each new builder begins with the manager's
immutable configuration, which remains unchanged by previous drafts.

For multiple issuers, use complete `emitter()` overrides on each builder or
`$manager->withConfig($typedConfig)`. The latter returns a new manager. An event
inherits only the configured emitter tax ID and can replace it with `emitter()`;
its explicit number range owns its LED.
