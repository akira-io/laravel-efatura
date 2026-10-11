# Currency conversion

A fiscal document is always issued in CVE. Another currency can only appear in the
amount to pay, as a `PayableAlternativeAmount` with its `CurrencyCode` and
`ExchangeRate` (Manual v11, printed pages 19 and 51-52). The `ExchangeRate` is the
BCV rate at the moment of issue, in CVE per one unit of the foreign currency,
greater than zero and with at most five decimals, the `DECIMAL` type of the Manual
(printed page 58).

`ConvertDocumentCurrencyAction` turns a document written in a foreign currency
into the CVE document the platform accepts, and records the original amount to
pay as the alternative.

```php
$document = ElectronicInvoiceData::from($payloadInEuros);

$cve = resolve(ConvertDocumentCurrencyAction::class)->handle($document, 'EUR');
$cve = resolve(ConvertDocumentCurrencyAction::class)->handle($document, 'EUR', $quote);
```

## Rates

A rate is asked for with an `ExchangeRateRequest` and answered with an
`ExchangeRateQuote`:

| Value | Meaning |
| --- | --- |
| `sourceCurrency`, `targetCurrency` | Upper case codes of the official currency catalog. The target defaults to `CVE`. |
| `effectiveAt` | The instant the rate must be valid for. The action uses the issue date and time of the document in Cabo Verde. |
| `rateType` | `ExchangeRateType::Buy`, `Sell`, `Reference` or `Custom`. Null in a request lets the provider use its own default. |
| `rate` | A `BigDecimal`, CVE per one unit of the source currency, rounded half up to five decimals. Given as a string it must be a plain decimal of at most 32 characters, without exponent or spaces. A rate that rounds to zero, is negative, is not a plain decimal or has more than 15 integer digits fails (`exchange_rate.rate_invalid`). |
| `effectiveAt` of the quote | The publication day (BCV), the first day of the observed year (World Bank) or the requested instant (fixed). It is never after the request. |
| `retrievedAt`, `provider` | When and from whom the rate was obtained. |
| `sourceUrl` | An HTTPS URL without credentials or fragment, or null. |
| `evidence` | A flat map of strings describing the observation (World Bank). Keys ending in `_url` hold HTTPS URLs without credentials or fragment. It never holds secrets. |

`ExchangeRateQuote::answering($request)` refuses a quote for another pair or rate
type (`exchange_rate.pair_mismatch`) and a quote dated after the request
(`exchange_rate.date_unavailable`). Every provider, the cache and the action call it.

A provider implements `Contracts\ExchangeRateProvider::rate(ExchangeRateRequest): ExchangeRateQuote`.
The container binds the contract to the provider selected by
`efatura.exchange_rates.provider`. There is no fallback chain: when the selected
provider fails, the conversion fails.

| Provider | Class | Rates | Default type |
| --- | --- | --- | --- |
| `bcv` (default) | `Currency\BcvExchangeRateProvider` | The table of the Banco de Cabo Verde page, `COMPRA` or `VENDA` divided by `UNIDADES` (JPY is published per 100) | `buy` |
| `world_bank` | `Currency\WorldBankExchangeRateProvider` | Indicator `PA.NUS.FCRF` (official rate, local currency per USD, annual average), crossed through USD | `reference` |
| `fixed` | `Currency\FixedExchangeRateProvider` | The configured map | `custom` |
| `callback` | `Currency\CallbackExchangeRateProvider` | A class with `__invoke(ExchangeRateRequest): ExchangeRateQuote` resolved from the container | the callback's |

### BCV

The page is read with the `efatura.http.bcv` client profile: its timeouts and TLS
options, no redirects, at most 256 KiB of body, and read retries. The address is
`efatura.http.bcv.base_url` when set, otherwise the official statistics page
`https://www.bcv.cv/pt/PoliticaMonetaria/EstatisticasCambiais/Paginas/Estatisticas_Cambiais.aspx?_expType=PDF`
(the address the Node package reads, asked for as `text/html`). The configured
address is checked like every base URL (HTTPS, no credentials, no fragment), except
that it may carry a query string, so the official address can be set explicitly;
it must also be on `www.bcv.cv` without a port (`exchange_rate.source_required`
otherwise, before any request). A quote's `sourceUrl` and the evidence URLs follow
the same rule: HTTPS, no credentials, no fragment, a query string allowed.

The parser reads the HTML with `Dom\HTMLDocument` (the HTML5 parser of ext-dom,
independent of the libxml version). A page with more than 20 tables or more than
500 rows is refused before any table is read. It requires exactly one table with
one header row naming `PAIS`, `MOEDA`, `UNIDADES`, `COMPRA` and `VENDA` once each (accents and
letter case ignored) and exactly one publication heading
`Taxas de Câmbio para o dia dd/mm/yyyy`, either a header cell spanning the five
columns inside that table or the heading element right before it. Only the rows of
that table are read, not those of a table nested in one of its cells. Numbers use a
decimal comma; spaces, non-breaking spaces and matching quotes around a number
are ignored. A page in another encoding must say so in a `meta charset`.

Only the publication of the same Cabo Verde calendar day as the request is
accepted. While the page still shows an earlier day (the BCV has not published
today's table yet) and `max_publication_age_days` is zero, the failure is
`exchange_rate.date_unavailable` marked retryable, with the context source `bcv`:
asking again later can succeed. On days the BCV does not publish (weekends and
holidays) it keeps failing until `max_publication_age_days` allows the last
publication. With `max_publication_age_days` above zero, an earlier publication up to
that many days old is accepted, and an older one fails with `exchange_rate.stale`.
A publication after the requested day fails with `exchange_rate.date_invalid`.

### World Bank

The year observed is set by `world_bank.observation_year`: `previous` (the
default) reads the year before the Cabo Verde year of the request, the latest
annual average the World Bank usually has; `request` reads the request's own year,
which is often not published until well into the next year. That observation is read
for each non-USD leg from `https://api.worldbank.org/v2/country/{economy}/indicator/PA.NUS.FCRF`.
The rate is `target per USD / source per USD`. CVE is always the economy `CPV`;
every other currency needs an entry in `world_bank.economies` (for example
`EUR => EMU`). A missing or null observation, or one of another year, fails with
`exchange_rate.date_unavailable`; the provider never falls back to another year.
The quote's `effectiveAt` is the first day of the year observed, and its evidence
records that year (`observation_period`) and the rule that chose it
(`observation_year`).

The address is fixed to `https://api.worldbank.org`: the `efatura.http.world_bank`
client profile carries only timeouts, retries and TLS options, and a `base_url`
set there fails with `configuration.fixed_endpoint` when the configuration loads.

### Cache

BCV and World Bank quotes are cached in `efatura.cache.store` for
`efatura.cache.exchange_rates_ttl_seconds`, under
`{prefix}:exchange_rates:{variant}:{source}:{target}:{type}:{Cabo Verde day}`. The
variant names the provider and the option that changes its answer: `bcv:age_{n}`
for a `max_publication_age_days` of n, `world_bank:{observation_year}`. The type is
the request's, or the provider's default when the request names none (`buy` or
`sell` as configured for the BCV, `reference` for the World Bank), so both share one
entry. A BCV quote published before the requested day is returned but never cached,
so the table published later that day is not hidden behind it; World Bank quotes,
always dated on the first day of the observed year, are cached. The entry holds plain
values and is rebuilt and checked against each request; an entry that cannot be
rebuilt is ignored, and a store that fails to read or write is treated as a miss.
Fixed and callback rates are not cached.

## Configuration

```php
'exchange_rates' => [
    'provider' => env('EFATURA_EXCHANGE_RATE_PROVIDER', 'bcv'),
    'bcv'      => [
        'rate_type'                => env('EFATURA_BCV_RATE_TYPE', 'buy'),
        'max_publication_age_days' => env('EFATURA_BCV_MAX_PUBLICATION_AGE_DAYS', 0),
    ],
    'world_bank' => [
        'economies'        => [],
        'observation_year' => env('EFATURA_WORLD_BANK_OBSERVATION_YEAR', 'previous'),
    ],
    'fixed'      => ['rates' => [], 'rate_type' => 'custom', 'name' => 'Fixed rate'],
    'callback'   => null,
],
```

| Key | Accepts | Error |
| --- | --- | --- |
| `provider` | `bcv`, `world_bank`, `fixed`, `callback` in any letter case, or an `ExchangeRateSource` case | `configuration.invalid_exchange_rate_provider` |
| `bcv.rate_type` | `buy` or `sell` | `configuration.invalid_rate_type` |
| `bcv.max_publication_age_days` | A nonnegative integer | `configuration.invalid_integer` |
| `world_bank.economies` | A map of codes of the official currency catalog (any letter case, normalized to upper case) to two or three letter or digit economy codes; `CVE` may only be restated as `CPV` | `configuration.invalid_type` |
| `world_bank.observation_year` | `previous` or `request`, in any letter case | `configuration.invalid_observation_year` |
| `fixed.rates` | A map of codes of the official currency catalog (any letter case, normalized to upper case) to positive decimal strings or integers | `configuration.invalid_type` |
| `fixed.rate_type` | Any `ExchangeRateType` value | `configuration.invalid_rate_type` |
| `fixed.name` | A nonempty string | `configuration.empty_string` |
| `callback` | An existing class name, required by the `callback` provider | `configuration.invalid_type` |

## Conversion

The action accepts any document with totals (`HasTotals`): invoices,
invoice-receipts, sales receipts, credit and debit notes, return and registration
notes. Its amounts are typed as CVE `Money`, so the source document carries the
foreign amounts as numbers in those fields, validated by the same fiscal rules;
the source currency is the second argument.

1. A source currency outside the official catalog fails with
   `exchange_rate.currency_unsupported`; a document without totals with
   `exchange_rate.invoice_invalid`.
2. A CVE source returns a revalidated copy, without asking for a rate and without
   adding an alternative.
3. A document that already has a `PayableAlternativeAmount` fails with
   `exchange_rate.alternatives_conflict`, before any rate is asked for.
4. The quote is the one given, or the provider's answer for the issue instant. It
   must answer that request. The provider bound to the contract is built only
   here, when a quote is needed: a CVE source or a given quote never builds it, so
   a misconfigured provider does not stop those conversions.
5. Only the independent inputs are converted from the source currency, by
   multiplying by the rate. The unit `price` of each line is rounded half up to
   five decimals, the most the Manual's `DECIMAL` type allows (printed page 58),
   so that a sub-cent price times a large quantity keeps its value (10 000 units
   of 0.001 EUR at 110.265 give 0.11027 CVE each and an extension of 1102.70,
   where a price rounded to cents would give 1100.00). Amount
   line discounts, absolute `taxAmount` values, an amount global discount, the
   amount to pay, reference `paymentAmount` values and their taxes, and payment
   `paymentAmount` values are rounded half up to two decimals. Quantities,
   percentages and percentage discounts do not change.
6. Every derived line value is recomputed from the converted inputs with the
   formulas and rounding of the totals verification:
   - `priceExtension` is the converted price times the quantity, rounded to two
     decimals;
   - the line base is that extension less the line discount (the converted amount,
     or the extension times the percentage);
   - `netTotal` is the base less a percentage global discount, rounded to two
     decimals (deduction lines take no global discount);
   - with an amount global discount, each normal or charge line keeps its share:
     the source allocation (base less net) is converted, and the cents left over
     by rounding go one at a time to the line with the most room (or, when the
     shares exceed the converted discount, are taken from the largest share), so
     the shares add up exactly to the converted discount;
   - a percentage tax is the net total times the percentage, rounded to two
     decimals; its `taxTotal` is that amount, and stays empty when it rounds to
     zero.
7. Totals are recomputed from the converted lines with the signs the totals
   verification uses: deduction lines subtract, information lines count only
   their taxes other than income tax, and income tax goes to the withholding. Tax
   and withholding totals are the sum of the taxes rounded per line. Charge and
   discount totals are recomputed only when the source had them.
8. The amount to pay is the source amount to pay converted directly. The
   difference against net total + taxes - withholding goes to
   `PayableRoundingAmount`, which stays null when the source had none and the
   difference is zero. When the source payments add up exactly to the source
   amount to pay, the converted payments add up exactly to the converted amount
   to pay: each is converted and truncated to cents, and the cents still missing
   go one each to the payments with the largest remainders (0.15 and 1.00 EUR at
   110.265 give 16.54 and 110.26, not 16.54 and 110.27). Otherwise each payment
   is rounded on its own.
9. One `PayableAlternativeAmount` is appended with the source amount to pay, the
   source currency and the rate. The XML writes it without non-significant zeros,
   for a currency without decimals as well (`1150` JPY).
10. The result is built again through the fiscal validation. A failure becomes
    `exchange_rate.invoice_invalid`, with the `ValidationException` as previous.

The source document is never modified.

This differs from the Node package, which converts every line amount on its own
(`priceExtension`, `netTotal` and `taxTotal` included). This package's totals
verification checks each line against itself, so converting derived values
independently would refuse documents whose extension is not the converted price
times the quantity: 10 000 units of 0.001 EUR at 110.265 give a price of 0.11027
(five decimals at most) and an extension of 1102.70, while the extension converted
on its own is 1102.65. Recomputing them keeps the converted document reconciled;
the cost is that a converted extension can differ from the source extension
converted on its own by the price rounding times the quantity, a difference that
ends in `PayableRoundingAmount`.

The conversion still fails with `exchange_rate.invoice_invalid` when a converted
input cannot be represented: an absolute tax amount or a payment amount that
rounds to zero at the rate, an amount global discount combined with a
percentage line discount whose base has more than five decimals, or an amount
global discount whose converted line shares differ from it by more than one
cent per line (checked before any cent is moved).

## Errors

Every failure is an `ExchangeRateException` (an `EfaturaException`) whose
`errorCode` and message are the code below and whose `failure` is the
`ExchangeRateFailure` case. Its `context` holds only the source (`bcv`,
`world_bank`, `callback`) and an HTTP status; it never holds a response body, a
URL with credentials or a redirect location. A callback that throws something
other than an `ExchangeRateException` keeps that exception, unchanged, as
`previous`, so its message and trace reach whatever logs the failure. The
package cannot see inside it: keep tokens, keys and response bodies out of the
messages your callback throws.

| Code | Retryable | When |
| --- | --- | --- |
| `exchange_rate.provider_unavailable` | yes | Connection failure, a non-2xx answer (redirects included), or a callback that threw something other than an `ExchangeRateException` (kept as previous) |
| `exchange_rate.response_invalid` | no | A body over 256 KiB (BCV) or 1 MiB (World Bank), a BCV page with more than 20 tables or 500 rows, a page or JSON that cannot be read with certainty, a quote without a provider name or with empty evidence, a negative publication age, an invalid economy map |
| `exchange_rate.currency_unsupported` | no | A currency outside the official catalog, not published by the BCV, not mapped for the World Bank or absent from the fixed rates |
| `exchange_rate.pair_mismatch` | no | A quote for another pair or rate type, a BCV request for another target or a type other than buy and sell, a World Bank request for a type other than reference |
| `exchange_rate.rate_invalid` | no | A rate or unit count that is not a positive decimal, or a rate that rounds to zero |
| `exchange_rate.date_unavailable` | only for the BCV case | The BCV page still shows an earlier day with `max_publication_age_days` at zero (retryable), no observation for the year, or a quote dated after the request |
| `exchange_rate.date_invalid` | no | A BCV publication dated after the requested day |
| `exchange_rate.stale` | no | A BCV publication older than `max_publication_age_days` |
| `exchange_rate.source_required` | no | A source URL that is not HTTPS on the expected host, or that carries credentials or a fragment |
| `exchange_rate.invoice_invalid` | no | A document without totals, or a converted document the fiscal validation refuses |
| `exchange_rate.alternatives_conflict` | no | A source document that already has a payable alternative amount |

---

[Documentation index](README.md) · [Fiscal domain](fiscal-domain.md) · [Transport](transport.md)
