# Configuration

`config/efatura.php` is published by `efatura:install` (or
`php artisan vendor:publish --tag=efatura-config`). The service provider loads
it into the immutable `Akira\Efatura\Configuration\EfaturaConfig` when the
application boots and fails with a `configuration.*` code on the first invalid
value (see [troubleshooting](17-troubleshooting.md)). The package never calls
`env()` outside this file, so `php artisan config:cache` is supported; the cached
file then holds the secrets below, so protect it like `.env`.

Each table lists the key under `efatura.`, its type, its default, the
environment variable that sets it, and what it means. A dash means the key has
no environment variable and is set in the file.

## Environment and driver

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `environment` | string, int or `Environment` | `test` | `EFATURA_ENVIRONMENT` | Repository the documents belong to: `test` (3), `homologation` (2) or `production` (1), as a name in any letter case or a code. It selects the repository code written in the IUD and the XML; it never changes a client URL. |
| `driver` | string or `TransportDriver` | `middleware` | `EFATURA_DRIVER` | `middleware` submits through the local e-Fatura middleware, which holds the OAuth credentials; `platform` submits directly with access tokens obtained from refresh tokens the application stores (see [transport](09-transport.md)). |

## Emitter

The default emitter of the documents the builders create. All-null fields mean
no default, and the whole section may be `null` in a multi-emitter
application; a builder can always supply a complete replacement (see
[builders](04-builders.md)). Partial identities are accepted at boot; the
required fiscal fields are checked when a document is built.

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `emitter.tax_id` | ?string | null | `EFATURA_EMITTER_TAX_ID` | Nine-digit NIF of the emitter. |
| `emitter.name` | ?string | null | `EFATURA_EMITTER_NAME` | Registered name. |
| `emitter.led` | ?string | null | `EFATURA_EMITTER_LED` | Lógica de Emissão de DFE, a decimal string from 1 to 99999. |
| `emitter.address.country_code` | ?string | null | `EFATURA_EMITTER_COUNTRY_CODE` | ISO country code; `CV` requires `address_detail` and an official `address_code`. |
| `emitter.address.region` | ?string | null | `EFATURA_EMITTER_REGION` | Region. |
| `emitter.address.city` | ?string | null | `EFATURA_EMITTER_CITY` | City. |
| `emitter.address.street` | ?string | null | `EFATURA_EMITTER_STREET` | Street. |
| `emitter.address.postal_code` | ?string | null | `EFATURA_EMITTER_POSTAL_CODE` | Postal code. |
| `emitter.address.address_detail` | ?string | null | `EFATURA_EMITTER_ADDRESS_DETAIL` | Free address detail, required for a CV address. |
| `emitter.address.address_code` | ?string | null | `EFATURA_EMITTER_ADDRESS_CODE` | Official address code from the address catalog, required for a CV address. |
| `emitter.address.state` | ?string | null | `EFATURA_EMITTER_STATE` | State. |
| `emitter.address.street_detail` | ?string | null | `EFATURA_EMITTER_STREET_DETAIL` | Street detail. |
| `emitter.address.building_name` | ?string | null | `EFATURA_EMITTER_BUILDING_NAME` | Building name. |
| `emitter.address.building_number` | ?string | null | `EFATURA_EMITTER_BUILDING_NUMBER` | Building number. |
| `emitter.address.building_floor` | ?string | null | `EFATURA_EMITTER_BUILDING_FLOOR` | Floor. |
| `emitter.contacts.email` | ?string | null | `EFATURA_EMITTER_EMAIL` | E-mail; contacts need an e-mail and a telephone or mobile. |
| `emitter.contacts.telephone` | ?string | null | `EFATURA_EMITTER_TELEPHONE` | Telephone. |
| `emitter.contacts.mobile` | ?string | null | `EFATURA_EMITTER_MOBILE` | Mobile. |
| `emitter.contacts.telefax` | ?string | null | `EFATURA_EMITTER_TELEFAX` | Telefax. |
| `emitter.contacts.website` | ?string | null | `EFATURA_EMITTER_WEBSITE` | Website. |

No emitter field is copied from the transmitter.

## Transmitter

The transmitter authenticates and submits. It may be the emitter itself or an
authorized intermediary with its own identity; the two roles stay separate even
when the NIFs match. Secrets default to null and are required only by the
operation that uses them; never log them.

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `transmitter.tax_id` | ?string | null | `EFATURA_TRANSMITTER_TAX_ID` | Nine-digit NIF of the transmitter. |
| `transmitter.name` | ?string | null | `EFATURA_TRANSMITTER_NAME` | Transmitter name. |
| `transmitter.middleware_key` | ?string (secret) | null | `EFATURA_TRANSMITTER_KEY` | 64-character key sent to the middleware as `cv-ef-mw-core-transmitter-key`. Required by the `middleware` driver. |
| `transmitter.oauth.client_id` | ?string | null | `EFATURA_TRANSMITTER_CLIENT_ID` | OAuth client of the transmitter on the platform identity provider. Required by the `platform` driver to exchange a refresh token for an access token. |
| `transmitter.oauth.client_secret` | ?string (secret) | null | `EFATURA_TRANSMITTER_CLIENT_SECRET` | Secret of that client, required with it. |
| `transmitter.oauth.issuer_url` | string | `https://iam.efatura.cv/auth/realms/taxpayers` | `EFATURA_OAUTH_ISSUER_URL` | Issuer whose OpenID configuration locates the token endpoint; HTTPS without credentials, query string or fragment. |

## Registered software

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `software.code` | ?string | null | `EFATURA_SOFTWARE_CODE` | Code of the software registered for transmission. |
| `software.name` | ?string | null | `EFATURA_SOFTWARE_NAME` | Its name. |
| `software.version` | ?string | null | `EFATURA_SOFTWARE_VERSION` | Its version. Supply all three when an operation needs the software identity. |

## Certificates

Paths are relative to the disk, without traversal. Keep private keys on a
private disk (see [signing](07-signing.md)).

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `certificates.disk` | ?string | null (`filesystems.default`) | `EFATURA_CERTIFICATES_DISK` | Disk holding the signing material, independent of document storage. |
| `certificates.certificate_path` | ?string | null | `EFATURA_CERTIFICATE_PATH` | PEM certificate, or the PKCS#12 bundle holding certificate and key. |
| `certificates.private_key_path` | ?string | null | `EFATURA_PRIVATE_KEY_PATH` | PEM private key; null with a PKCS#12 bundle. |
| `certificates.passphrase` | ?string (secret) | null | `EFATURA_PRIVATE_KEY_PASSPHRASE` | Passphrase of the key or bundle, kept exactly, whitespace included. |
| `certificates.ca_bundle_path` | ?string | null | `EFATURA_CA_BUNDLE_PATH` | Optional PEM CA bundle on the same disk; when set, the certificate chain is verified against that bundle alone, never the system store. |

## Storage

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `storage.disk` | ?string | null (`filesystems.default`) | `EFATURA_STORAGE_DISK` | Disk where `StoreIssuedArtifactsAction` keeps the signed XML, the ZIP, the answer and the DFA. A public disk is refused. |
| `storage.path` | string | `efatura` | `EFATURA_STORAGE_PATH` | Nonempty relative directory without traversal; files land in `{path}/{repository}/{NIF}/{YYYY}/{MM}/{IUD}/` (see [artifact storage](14-storage.md)). |

## Cache

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `cache.store` | ?string | null (`cache.default`) | `EFATURA_CACHE_STORE` | Store for exchange-rate quotes, the OpenID configuration, platform access tokens (encrypted with the application key) and the locks that guard a token refresh and an artifact write. Tags are not required. |
| `cache.prefix` | string | `efatura` | `EFATURA_CACHE_PREFIX` | Nonempty prefix isolating the package entries. |
| `cache.exchange_rates_ttl_seconds` | int | `3600` | `EFATURA_EXCHANGE_RATES_TTL_SECONDS` | Positive lifetime of a cached exchange-rate quote, in seconds. |

Signing material, refresh tokens, client secrets and the middleware key are
never cached.

## Exchange rates

Rates are CVE per one unit of the foreign currency, at five decimals. One
provider is selected and there is no fallback (see
[currency conversion](12-currency.md)).

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `exchange_rates.provider` | string | `bcv` | `EFATURA_EXCHANGE_RATE_PROVIDER` | `bcv`, `world_bank`, `fixed` or `callback`. |
| `exchange_rates.bcv.rate_type` | string | `buy` | `EFATURA_BCV_RATE_TYPE` | `buy` or `sell` column of the Banco de Cabo Verde page. |
| `exchange_rates.bcv.max_publication_age_days` | int | `0` | `EFATURA_BCV_MAX_PUBLICATION_AGE_DAYS` | When positive, a publication of an earlier Cabo Verde day up to this age is accepted; zero requires the requested day. |
| `exchange_rates.world_bank.economies` | array | `[]` | - | Map of currency to World Bank economy code (`'EUR' => 'EMU'`); CVE is always `CPV`. |
| `exchange_rates.world_bank.observation_year` | string | `previous` | `EFATURA_WORLD_BANK_OBSERVATION_YEAR` | `previous` (the year before the request, usually published) or `request` (the request's own year). |
| `exchange_rates.fixed.rates` | array | `[]` | - | Map of currency to a positive decimal string rate. |
| `exchange_rates.fixed.rate_type` | string | `custom` | - | Rate type reported by fixed quotes: `buy`, `sell`, `reference` or `custom`. |
| `exchange_rates.fixed.name` | string | `Fixed rate` | - | Source name reported by fixed quotes. |
| `exchange_rates.callback` | ?class-string | null | - | Invokable class resolved from the container when the provider is `callback`. |

## DFA

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `dfa.base_url` | string | `https://pe.efatura.cv/dfe/view` | `EFATURA_DFA_BASE_URL` | Base of the QR code URL `{base_url}/{IUD}` (Manual v11, 6.13); HTTPS without credentials, query string or fragment, trailing slashes removed. |
| `dfa.cache_path` | ?string | null (`storage/framework/cache/efatura-dompdf`) | `EFATURA_DFA_CACHE_PATH` | Absolute directory for the dompdf font metrics and temporary files; created when missing, and an existing one must belong to the PHP user and be closed to group and others. |

## Database and queue

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `database.connection` | ?string | null (`database.default`) | `EFATURA_DATABASE_CONNECTION` | Connection of the sequence table. Numbers are never reserved while it is inside a transaction. |
| `database.sequences_table` | string | `efatura_sequences` | `EFATURA_SEQUENCES_TABLE` | SQL identifier of letters, digits and underscores (see [sequences](06-sequences.md)). The published migration reads both keys when it runs. |
| `queue.connection` | ?string | null (`queue.default`) | `EFATURA_QUEUE_CONNECTION` | Queue connection reserved for the package jobs; validated and exposed on `EfaturaConfig::$queue`. The package dispatches no job itself yet. |
| `queue.queue` | ?string | null | `EFATURA_QUEUE` | Queue name; null inherits the name of the selected connection, and stays null for drivers such as `sync` that have none. |

## HTTP

The global defaults apply to every client profile whose own value is null.
Retries apply to reads only (document lookups, OpenID discovery, remote
readiness): a submission, a self-billing request or a token request is never
repeated, since the platform offers no idempotency key. Requests go through
the Laravel HTTP client; hide the `authorization` and
`cv-ef-mw-core-transmitter-key` headers in Telescope or Nightwatch.

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `http.timeout_seconds` | int | `30` | `EFATURA_HTTP_TIMEOUT_SECONDS` | Positive total timeout. |
| `http.connect_timeout_seconds` | int | `10` | `EFATURA_HTTP_CONNECT_TIMEOUT_SECONDS` | Positive connection timeout. |
| `http.retries` | int | `2` | `EFATURA_HTTP_RETRIES` | Additional attempts of a read; zero disables retries. |
| `http.retry_delay_milliseconds` | int | `200` | `EFATURA_HTTP_RETRY_DELAY_MILLISECONDS` | Nonnegative delay between attempts. |
| `http.concurrency` | int | `5` | `EFATURA_HTTP_CONCURRENCY` | Positive bound on simultaneous requests of one client, validated and exposed on `HttpClientConfig::$concurrency`; no package operation sends concurrent requests yet. |
| `http.verify_tls` | bool | `true` | `EFATURA_HTTP_VERIFY_TLS` | TLS verification. Disabling it exposes credentials and documents to interception. |
| `http.ca_bundle_path` | ?string | null | `EFATURA_HTTP_CA_BUNDLE_PATH` | Absolute path of a readable PEM file; when set, only those certificates are trusted. Cannot be combined with disabled verification. |

Each client profile has the same tuning keys, null meaning the global default,
plus its address:

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `http.middleware.base_url` | ?string | `https://localhost:3443/v1` | `EFATURA_MIDDLEWARE_BASE_URL` | Local middleware API, ending with `/v1`. |
| `http.middleware.ca_bundle_path` | ?string | null | `EFATURA_MIDDLEWARE_CA_BUNDLE_PATH` | PEM file that verifies the self-signed middleware certificate. |
| `http.platform.base_url` | ?string | `https://services.efatura.cv/v1` | `EFATURA_PLATFORM_BASE_URL` | Platform API, ending with `/v1`. |
| `http.platform.ca_bundle_path` | ?string | null | - | PEM file trusted for the platform. |
| `http.bcv.base_url` | ?string | null (the official statistics page) | `EFATURA_BCV_BASE_URL` | Exchange-rate page on `www.bcv.cv`; the only base URL that may carry a query string. |
| `http.bcv.ca_bundle_path` | ?string | null | - | PEM file trusted for the BCV page. |
| `http.world_bank.ca_bundle_path` | ?string | null | - | PEM file trusted for the World Bank API, whose address `https://api.worldbank.org` is fixed: the profile takes no `base_url`, and setting one fails. |

Base URLs use HTTPS without credentials, query strings or fragments, and a null
base URL defers endpoint selection to the provider. The tuning keys, all `?int`
or `?bool` with a null default and no environment variable:

| Middleware | Platform | BCV | World Bank |
| --- | --- | --- | --- |
| `http.middleware.timeout_seconds` | `http.platform.timeout_seconds` | `http.bcv.timeout_seconds` | `http.world_bank.timeout_seconds` |
| `http.middleware.connect_timeout_seconds` | `http.platform.connect_timeout_seconds` | `http.bcv.connect_timeout_seconds` | `http.world_bank.connect_timeout_seconds` |
| `http.middleware.retries` | `http.platform.retries` | `http.bcv.retries` | `http.world_bank.retries` |
| `http.middleware.retry_delay_milliseconds` | `http.platform.retry_delay_milliseconds` | `http.bcv.retry_delay_milliseconds` | `http.world_bank.retry_delay_milliseconds` |
| `http.middleware.concurrency` | `http.platform.concurrency` | `http.bcv.concurrency` | `http.world_bank.concurrency` |
| `http.middleware.verify_tls` | `http.platform.verify_tls` | `http.bcv.verify_tls` | `http.world_bank.verify_tls` |

## Diagnostics routes

Optional JSON routes for readiness and XML validation, off by default (see
[diagnostics routes](16-http.md)).

| Key | Type | Default | Environment | Meaning |
| --- | --- | --- | --- | --- |
| `routes.enabled` | bool | `false` | `EFATURA_ROUTES_ENABLED` | Registers `GET {prefix}/readiness` and `POST {prefix}/validate`. |
| `routes.prefix` | string | `efatura` | - | URI path prefix without parameters. |
| `routes.name` | string | `efatura.` | - | Route name prefix, ending with a dot. |
| `routes.middleware` | list | `[]` | - | Middleware that authenticates and authorizes the caller. Enabling the routes with an empty list fails with `configuration.unprotected_routes`. |
| `routes.max_upload_kilobytes` | int | `5120` | - | Upload limit of the validation route, at most 5120 (5 MiB). |
| `routes.remote_readiness` | bool | `false` | - | Lets the readiness route reach the configured endpoint too; off so an HTTP request never triggers outbound traffic. |

---

Previous: [Installation](01-installation.md) · [Index](00-index.md) · Next: [Fiscal domain](03-fiscal-domain.md)
