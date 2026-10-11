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
  notion of who may call them, so the application authenticates and authorizes
  the caller. An empty list fails configuration loading with
  `configuration.unprotected_routes`, and the application does not boot.
- `prefix` is a URI path without parameters; surrounding slashes are removed.
  Anything else fails with `configuration.invalid_route_prefix`.
- `name` is the route name prefix and ends with a dot, otherwise
  `configuration.invalid_route_name`.
- `max_upload_kilobytes` limits the uploaded XML, from 1 to 5120 (5 MiB, the
  limit of `efatura:validate`).
- `remote_readiness` makes the readiness route also reach the endpoint of the
  configured driver. It is off by default, so a request never causes outbound
  traffic; there is no query parameter to switch it on.

The section is validated on every boot, enabled or not. With cached routes the
package registers nothing and the cached ones are used.

## Readiness

`GET {prefix}/readiness` runs `CheckReadinessAction` and returns
`ReadinessReport::toArray()` without a `data` wrapper, the same document as
`efatura:doctor --json`. See [readiness](readiness.md) for the checks and their
codes. Secrets, passphrases and private key paths never appear in it.

## Validation

`POST {prefix}/validate` takes a multipart upload:

- `file`: required, `application/xml`, `text/xml` or `text/plain`, up to
  `max_upload_kilobytes`;
- `profile`: optional, `enveloped` or `detached`, detected from the root
  element when omitted.

It runs `ValidateXmlDocumentAction`, the action behind `efatura:validate`, and
returns the same document as `efatura:validate --json`:

```json
{
    "valid": true,
    "profile": "enveloped",
    "schema": {"valid": true, "code": null, "violations": []},
    "signature": {
        "valid": true,
        "failures": [],
        "certificate": {"subject": "CN=...", "serial_number": "...", "digest": "..."}
    },
    "trust_chain_verified": false
}
```

The certificate chain is never verified against ICP-CV, which
`trust_chain_verified` states. A document the hardened parser refuses has a
`null` profile and signature and the refusal code in `schema.code`.

An upload that cannot be validated (missing, too large, of another type, or
with an unknown profile) answers 422 with `valid: false` and the Laravel
validation `errors`, in JSON whatever the `Accept` header says.

## Errors

A package exception raised while validating is reduced to its code in
`schema.code`; its message, its fields and its trace never reach the response.
Exceptions from outside the package are left to the application exception
handler.
