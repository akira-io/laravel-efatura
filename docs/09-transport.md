# Transport

A prepared archive reaches the e-Fatura platform (PE) through a
`Contracts\Transport`. The contract has three operations:

| Operation | Request | Manual v11 |
| --- | --- | --- |
| `submit(PackagedArchive $archive): SubmissionResult` | `POST dfe` or `POST event`, by the archive `kind` | §10.2, §10.4 (p.67-71) |
| `lookupDocument(string $iud, bool $selfBilled = false): DocumentLookup` | `GET dfe/xml/{IUD}` | §10.3 (p.70) |
| `authorizeSelfBilling(SelfBillingAuthorizationRequestData $request): SelfBillingAuthorization` | `POST dfe/self-billing/authorize` | §10.5 (p.71-73) |

The validation and authorization are synchronous (§5.4, p.12): the answer of a
submission is the outcome. Use the actions on top of the contract
(`SubmitArchiveAction`, `SubmitPreparedDocumentAction`, `SubmitPreparedEventAction`,
`ReconcileDocumentAction`, `RequestSelfBillingAuthorizationAction`, and the
[issuance](10-issuance.md) actions) rather than the transport itself: they check the
repository and dispatch the Laravel events.

## Drivers

`efatura.driver` (`EFATURA_DRIVER`) selects the transport the container binds to
`Contracts\Transport`:

| Driver | Class | Base URL (`efatura.http.<driver>.base_url`) | Credentials |
| --- | --- | --- | --- |
| `middleware` (default) | `Transport\MiddlewareTransport` | `https://localhost:3443/v1` | `cv-ef-mw-core-transmitter-key` header with `efatura.transmitter.middleware_key` (64 printable ASCII characters, §11.4.1, p.77) |
| `platform` | `Transport\PlatformTransport` | `https://services.efatura.cv/v1` | `Authorization: Bearer` from `Contracts\AccessTokenProvider` |

The middleware keeps the OAuth credentials of the transmitter and of each emitter
and asks for the scopes on their behalf (§11.5, p.79-81), which is why it is the
default. `Efatura::driver('platform')` and `EfaturaManager::driver()` return either
driver explicitly; `TransportResolver::driver(TransportDriver::Platform)` does the
same from the container. An application that binds its own `Contracts\Transport`
replaces the configured driver in every action and wherever the contract is
injected; `Efatura::driver()`, `EfaturaManager::driver()` and `TransportResolver`
still build the package drivers. There is no `extend()`: a custom driver is a
container binding.

Both base URLs carry the API version, and every request names a resource
relative to it. URLs come only from the configuration, never from an argument,
and redirects are never followed. Homologation and test have no hostnames of
their own: the repository travels in the `cv-ef-repository-code` header and in
the XML (§10.2, p.67).

## Requests

A submission is a `multipart/form-data` POST with a single part `file`
(`dfe.zip` or `event.zip`, `application/octet-stream`) holding the archive bytes,
the `cv-ef-repository-code` header with the repository of the archive identifiers
and `Accept: application/xml`. The middleware follows the same contract as the
platform (§11.6, p.83). `SubmitArchiveAction` refuses an archive whose repository
differs from `efatura.environment` with `transport.repository_mismatch` before any
request, so a configuration change between preparing and sending never sends a
test document to production.

Every operation builds a fresh request from the HTTP client profile: base URL,
`timeout_seconds`, `connect_timeout_seconds`, `verify_tls` or `ca_bundle_path`,
and no redirects. Requests always go through the Guzzle cURL handler (ext-curl is
required), never through PHP streams: only cURL says whether a failed request left
the machine. The handler keeps at most 5 MiB and one byte of the response body
(`Fiscal::MAX_RESPONSE_BYTES`) and discards the rest as it arrives; a larger body
is `transport.response_too_large`. Nothing is shared between two emitters.

## Responses

The PE answers a submission with `<Responses>` holding one
`<Response Succeeded="true|false">` per entry (figure 13, p.69). The package reads
exactly that shape: `Succeeded` is `true`, `false`, `1` or `0`, every entry of the archive has
exactly one `Response` whose `EntryName` matches it, a success carries an
`AuthorizationCode`, and a failure carries at least one `Message` with `Source`,
`Type` and `Code`. Any other body, including HTML from a proxy, JSON, a
`DOCTYPE`, malformed or non UTF-8 XML, is `transport.unexpected_response`; it
never becomes a success. Comments and processing instructions between elements
are ignored.

`SubmissionResult` holds the `kind`, the HTTP `status`, one `EntryResult` per
entry in archive order (`entryName`, `identifier`, `succeeded`,
`authorizationCode`, `signatureTimeStamp`, `messages`) and `receivedAt`.
`outcome()` is `Accepted`, `Rejected` or `Partial`; `entry($identifier)` returns
the result of one IUD or event ID. `RemoteMessage` keeps `Source`, `Type`,
`DateTime`, `Code`, `Description` and `Location` as text, as the PE sent them.

## Failures

The Manual defines no HTTP status codes, so this table is package policy. A
readable `<Responses>` body wins over its status, and the status of an error answer
wins over a body too large to read (a 413 with a large body is still
`transport.payload_too_large`).

| Answer | `TransportException::$failure` | Retryable |
| --- | --- | --- |
| DNS failure, connection refused, connect timeout, TLS handshake (cURL 6, 7, 28 before connecting, 35, 60; the Guzzle 8 `ConnectTimeoutException`) | `transport.connection_failed` | yes: nothing was delivered |
| 401, 403 | `transport.unauthorized` | no |
| 404, 405, 415 | `transport.http_error` (endpoint misconfigured, nothing processed) | no |
| 413 | `transport.payload_too_large` | no |
| 429 | `transport.rate_limited` | yes |
| 3xx, 5xx, a connection broken or silent after the request left, any failure without a cURL error number, an unreadable body after a submission | `transport.outcome_unknown` | no: reconcile first |
| Body of a successful status that is not figure 13 | `transport.unexpected_response` | no: reconcile first |
| Body above 5 MiB on a successful status | `transport.response_too_large` | no: reconcile first |
| A lookup answered outside 2xx, except 404 (not found), 401 and 403 (`transport.unauthorized`), 413 and 429 as above | `transport.http_error` (a read processes nothing) | no |
| A lookup answered 2xx without the DFE of that IUD (including 206) | `transport.unexpected_response` | no |
| A self-billing answer with `succeeded` false | `transport.rejected`, with the platform `messages` | no |
| An archive of another repository than `efatura.environment`, before any request | `transport.repository_mismatch` | no |
| An archive of more than one emitter on the platform driver, before any request | `transport.mixed_emitters` | no |
| No middleware key, no OAuth client, no token provider, no `efatura.emitter.tax_id` for a self-billing request, before any request | `transport.missing_credentials` (the configuration key in `field`) | no |

The exception message is the error code. `context` holds only `status`, `kind`,
`identifiers` (comma separated) and `bytes`; the body, the headers and the
original Guzzle or Laravel exception never enter the exception or its `previous`
chain. [Issuance](10-issuance.md#outcomes) says what to do with each outcome.

## Retries

`efatura.http.*.retries` and `retry_delay_milliseconds` apply only to reads
(`lookupDocument`, OpenID discovery, remote readiness), and only after a
connection that was never established or after 429, 502, 503 or 504. A POST to
`dfe`, `event`, `dfe/self-billing/authorize` or the token endpoint is never
repeated automatically. The single exception is the platform driver: after a 401
it forgets the access token and resends once with a renewed one, because the PE
refuses that request before processing it. A second 401 is `transport.unauthorized`.

## Middleware TLS

The middleware listens on `https://localhost:3443` with a self-signed certificate
(§11.4, p.76-77). Set `efatura.http.middleware.ca_bundle_path`
(`EFATURA_MIDDLEWARE_CA_BUNDLE_PATH`) to the absolute path of a PEM file holding
that certificate: the client then trusts it alone instead of the system store.
`verify_tls` false also works, but sends the transmitter key to anyone able to
intercept the local network, and [readiness](11-readiness.md) reports it as a warning.

## OAuth

The platform supports only the Authorization Code flow with PKCE (§10, p.65), so
a client ID and secret cannot obtain a token on their own. `PlatformTransport` asks
a `Contracts\AccessTokenProvider` for a token of the taxpayer concerned: the emitter
NIF of the IUD for a document, the `EmitterTaxId` of the XML for an event, and the
configured `efatura.emitter.tax_id` (the buyer) for a self-billing authorization.
A self-billed document names the seller in its IUD, but the buyer is the one who
issues it and consented to the transmitter, so its submission
(`PackagedArchive::$selfBilled`) and its lookup (`lookupDocument($iud, selfBilled:
true)`, `ReconcileDocumentAction::handle($prepared)` on a self-billed
`PreparedDocument`, or `ReconcileDocumentAction::handle($iud, selfBilled: true)`) also use the
token of the configured `efatura.emitter.tax_id`; without it they fail with
`transport.missing_credentials`. The Manual does not say whose token the platform
expects here; this reading is still to be confirmed with the DNRE. An archive of
more than one emitter is refused with `transport.mixed_emitters`, except a
self-billed one, whose token is always the buyer's.
Scopes per operation: `cv_ef_dfe_create`, `cv_ef_event_create`,
`cv_ef_dfe_read_iud` and `cv_ef_dfe_self_billing_authorize` (table 22, p.66).

The package ships `OAuth\RefreshTokenAccessTokenProvider`, used whenever the
application binds no `Contracts\AccessTokenProvider` of its own but binds a
`Contracts\RefreshTokenRepository`, in any provider and at any time before the
first platform request:

```php
$this->app->bind(RefreshTokenRepository::class, EncryptedRefreshTokens::class);
```

The repository stores the refresh token each taxpayer granted with
`offline_access` (p.67) and receives the rotated one through `replace()`. The
package never stores refresh tokens. The provider reads the token endpoint from
`{issuer_url}/.well-known/openid-configuration` (cached for a day, HTTPS on the
issuer host only, checked again every time it is read from the cache), posts
`grant_type=refresh_token` with the transmitter client credentials, and caches the
access token encrypted with the application key in `efatura.cache.store`, under a
key hashed from client, NIF and scopes, for `expires_in` minus 60 seconds.

A taxpayer has one refresh token whatever the scopes, so a store that supports
locks serializes every renewal of the same client and NIF; the refresh token and
the cache are read again inside the lock. A rotated refresh token is handed to
`replace()` as soon as it is read, before the rest of the grant is checked. The
access and refresh tokens must follow the `b64token` syntax of RFC 6750. Without a
repository, the platform driver fails with `transport.missing_credentials` on
`efatura.transmitter.oauth`. The redirect flow that obtains the first refresh token
belongs to a later release.

`RefreshTokenRepository` must be durable and transactional: `replace()` returns only
once the new token is stored for good, because the issuer has already invalidated
the previous one. A token lost there makes the next renewal fail with
`oauth.token_refused` until the taxpayer grants access again.

| `OAuthException::$errorCode` | Cause |
| --- | --- |
| `oauth.discovery_failed` | The OpenID configuration could not be read, or names a token endpoint outside the issuer |
| `oauth.refresh_token_missing` | The repository holds no refresh token for the NIF; nothing is sent |
| `oauth.token_refused` | The token endpoint answered 400 or 401 (`invalid_grant`, revoked consent, wrong client) |
| `oauth.token_unavailable` | The token endpoint could not be reached or did not answer, or another renewal of the same NIF held the lock for longer than the platform timeouts. The document never left and `retryable` is true; if the issuer did answer, the refresh token may have rotated without reaching `replace()` |
| `oauth.unexpected_response` | Any other answer of the token endpoint, or a grant whose tokens or expiry are invalid |

`context` holds only `emitter` and `status`. Every OAuth failure happens before the
fiscal request leaves, so the issuance events report it as never processed.

## Self-billing

`RequestSelfBillingAuthorizationAction::handle()` validates a
`SelfBillingAuthorizationRequestData` and posts it as JSON:

```json
{"taxId": "200300400", "documentTypeCode": 1, "mobilePhoneNumber": "9912345", "totalAmount": "1500.5"}
```

`documentTypeCode` is the platform code of FTE, FRE, RCE, NCE, NDE or DVE (1, 2,
4, 5, 6 or 8) and `totalAmount` the CVE amount as decimal text. An answer with
`succeeded` true and a `payload` (figure 16) becomes a `SelfBillingAuthorization`;
its `iud` must be a valid IUD of the configured repository naming the seller, the
document type, the `ledCode` and the `documentNumber` of the payload, otherwise
the answer is `transport.unexpected_response`. Figure 16 gives no types, so
`succeeded` may also be the text `true` or `false`, and the expiration, `ledCode`
and `documentNumber` may be digits in a string. An answer with `succeeded` false
throws `transport.rejected` with the platform `messages` in
`TransportException::$messages` (each read from `source`, `type`, `dateTime`,
`code`, `description` and `location`, first letter in either case). The middleware
support of this resource is still to be confirmed in its resource list (p.77).
[Builders](04-builders.md#self-billed-documents) shows how to prepare the document
with the values received.

## Testing

`Testing\FakeTransport` implements the contract in memory:

```php
$transport = resolve(FakeTransport::class)
    ->accept($iud, 'AUTH-1')
    ->reject($otherIud, new RemoteMessage('BIZ', 'ERROR', null, 'BIZ-001', 'Refused.', null))
    ->fail($thirdIud, TransportFailure::OutcomeUnknown)
    ->holdDocument($iud, $xml)
    ->grantSelfBilling($authorization);
$this->app->instance(Transport::class, $transport);
```

Unprogrammed entries are accepted. `submitted()`, `lookedUp()` and
`selfBillingRequests()` return what the code under test sent. Tests of the HTTP
drivers use `Http::fake()`; the package test suite calls
`Http::preventStrayRequests()` and runs without network.

## Security

The middleware key, client secret, refresh token, access token and
`Authorization` header never appear in exception messages, `context`, `previous`,
events, the token cache in clear, `var_dump()`, `print_r()` or `json_encode()`
output. The package writes no logs.

The requests go through the container `Illuminate\Http\Client\Factory`, which is
what `Http::fake()` replaces and what dispatches `RequestSending` and
`ResponseReceived`. Laravel Telescope, Nightwatch or any listener of those events
can record the whole exchange, secrets included, and reads the whole response
body (at most the 5 MiB and one byte the handler keeps). Hide exactly these keys
there:

| Where | Keys |
| --- | --- |
| Request headers | `authorization`, `cv-ef-mw-core-transmitter-key` |
| Request parameters (the form posted to the token endpoint) | `client_secret`, `refresh_token` |
| Response parameters (the token grant) | `access_token`, `refresh_token`, `id_token` |

In Telescope that is `Telescope::hideRequestHeaders()`, `hideRequestParameters()`
and `hideResponseParameters()`; in Nightwatch, its redaction of outgoing requests.
Check that the outgoing request watcher of each tool honours them. Better still,
keep the fiscal hosts out of those watchers altogether: the middleware base URL
(`https://localhost:3443`), `services.efatura.cv` and the issuer host
(`iam.efatura.cv`), for example with a `Telescope::filter()` that drops client
request entries for them. Fiscal documents and self-billing requests carry
personal data: treat recorded bodies the same way.

---

Previous: [Packaging](08-packaging.md) · [Index](00-index.md) · Next: [Issuance](10-issuance.md)
