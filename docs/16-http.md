# Diagnostics routes

The package can expose two JSON routes for diagnostics. They are disabled by
default and only diagnose: nothing is numbered, signed, submitted or rendered
over HTTP. There are no routes for submitting documents or events, building
XML or printing the DFA, because those are fiscal operations the application
authorizes itself.

| Method | URI                  | Name                | Answers                                   |
|--------|----------------------|---------------------|-------------------------------------------|
| GET    | `{prefix}/readiness` | `{name}readiness`   | 200 when ready, 503 when not              |
| POST   | `{prefix}/validate`  | `{name}validate`    | 200 when valid, 422 when not or refused   |

## Enabling them

```php
'routes' => [
    'enabled'              => env('EFATURA_ROUTES_ENABLED', false),
    'prefix'               => 'efatura',
    'name'                 => 'efatura.',
    'middleware'           => ['auth:sanctum', 'can:efatura-diagnostics'],
    'max_upload_kilobytes' => 5120,
    'remote_readiness'     => false,
],
```

- `middleware` is mandatory once the routes are enabled. The package has no
  notion of who may call them, so the list must authenticate the caller (for
  example `auth:sanctum`) and authorize it (`can:...`); the package only checks
  that the list is not empty, so a list of middleware that do neither leaves the
  routes open. Add `throttle` as well (for example `throttle:10,1`): validation
  parses and verifies uploads of up to 5 MiB, and readiness may load the
  certificate and reach the database. An empty list fails configuration loading
  with `configuration.unprotected_routes`, and the application does not boot.
- `enabled` must be the boolean `true` or `false`. `EFATURA_ROUTES_ENABLED=true`
  is read as a boolean; `1`, `0`, `yes` or `on` are strings and fail with
  `configuration.invalid_type` at `efatura.routes.enabled`.
- `prefix` is a URI path without parameters; surrounding slashes are removed.
  Anything else fails with `configuration.invalid_route_prefix`.
- `name` is the route name prefix and ends with a dot, otherwise
  `configuration.invalid_route_name`.
- `max_upload_kilobytes` limits the uploaded XML, from 1 to 5120 (5 MiB, the
  limit of `efatura:validate`).
- `remote_readiness` makes the readiness route also reach the endpoint of the
  configured driver. It is off by default, so a request never causes outbound
  traffic; there is no query parameter to switch it on.

Only `enabled` is read while the routes are disabled, so a mistake in the rest
of the section never stops the application from booting; `efatura:doctor` and
the readiness report show it as a `Warning` of the configuration check with its
code and field. Once enabled, the whole section is validated on every boot and
an error stops the boot.

With cached routes the package registers nothing and the cached ones are used.
Run `php artisan route:cache` again after changing any `efatura.routes` value,
including the switch itself. Every package route also checks
`efatura.routes.enabled` on each request and answers 404 while it is false, so
routes left in a stale cache stop answering as soon as the configuration turns
them off.

## Readiness

`GET {prefix}/readiness` runs `CheckReadinessAction` and returns
`ReadinessReport::toArray()` without a `data` wrapper, the same document as
`efatura:doctor --json`. See [readiness](11-readiness.md) for the checks and their
codes. Secrets, passphrases and private key paths never appear in it.

## Validation

`POST {prefix}/validate` takes a multipart upload:

- `file`: required, `application/xml`, `text/xml` or `text/plain`, up to
  `max_upload_kilobytes`;
- `profile`: optional, `enveloped` or `detached`, detected from the root
  element when omitted.

PHP refuses an upload above `upload_max_filesize`, or a request above
`post_max_size`, before Laravel runs, and the route then reports the `file` as
missing. Set both in `php.ini` (and any limit of the web server, such as
`client_max_body_size` in nginx) above `max_upload_kilobytes`, with room for the
multipart overhead: for the default 5120 KB, `upload_max_filesize = 6M` and
`post_max_size = 8M`.

It runs `ValidateXmlDocumentAction`, the action behind `efatura:validate`, and
returns the same document as `efatura:validate --json`:

```json
{
    "valid": true,
    "profile": "enveloped",
    "schema": {"valid": true, "code": null, "violations": [], "violations_total": 0},
    "signature": {
        "valid": true,
        "failures": [],
        "certificate": {"subject": "CN=...", "serial_number": "...", "digest": "..."}
    },
    "trust_chain_verified": false
}
```

The certificate chain is never verified against ICP-CV, which
`trust_chain_verified` states. `schema.violations` lists the first 100
violations and `schema.violations_total` counts them all. As in the command, a
long decimal `ds:X509SerialNumber` refused by libxml before 2.11 is not reported. A document the hardened parser refuses has a
`null` profile and signature and the refusal code in `schema.code`. A document
the schema refuses has a `null` signature: the signature is only checked once
the schema accepts the document.

An upload that cannot be validated (missing, too large, of another type, or
with an unknown profile) answers 422 with `valid: false` and the Laravel
validation `errors`, in JSON whatever the `Accept` header says.

## Errors

When the bundled official schemas cannot be used (`artifacts.*`), the route
answers 503 with
`{"valid": false, "error": "artifacts_unavailable", "code": "artifacts.<reason>"}`:
the installation is at fault, not the document, which was not validated. See
[troubleshooting](17-troubleshooting.md#official-artifacts).

Any other package exception raised while validating is reduced to its code in
`schema.code`; its message, its fields and its trace never reach the response.
Exceptions from outside the package are left to the application exception
handler.

---

Previous: [Console commands](15-console.md) · [Index](00-index.md) · Next: [Troubleshooting](17-troubleshooting.md)
