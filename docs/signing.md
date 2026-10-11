# Signing

Manual 11 requires every DFE and every event to carry an XMLDSig signature with
the XAdES-BES properties (SIGN-R, p.40; §7.20, p.57). This page covers the
signing credentials, the checks made on them, and the exact shape of the
signature the package writes. The signer runs on ext-openssl and DOM only; no
XMLDSig library is loaded at runtime.

## Credentials

`Contracts\CertificateLoader::load(CertificateConfig $config): SigningCredentials`
reads the credentials from the disk `efatura.certificates.disk`. The container
binds it to `Signing\OpenSslCertificateLoader`. Two formats are accepted, told
apart by content:

- PEM: `certificate_path` holds the certificate (`-----BEGIN CERTIFICATE-----`)
  and `private_key_path` the private key. An encrypted key is opened with
  `passphrase`.
- PKCS#12: `certificate_path` holds the `.p12`/`.pfx` file with the certificate
  and the key, opened with `passphrase`, and `private_key_path` stays null.

```dotenv
EFATURA_CERTIFICATES_DISK=local
EFATURA_CERTIFICATE_PATH=efatura/certificate.p12
EFATURA_PRIVATE_KEY_PASSPHRASE=...
```

Paths are relative to the disk, without traversal. Keep the files on a private
disk. The files hold the credentials themselves: a file whose content is a
`file://` reference to another location fails with `certificate.invalid`, so
OpenSSL never reads outside the disk.

The loader then checks that:

- the key belongs to the certificate (`certificate.key_mismatch`);
- the key is RSA of at least 2048 bits (`certificate.key_unsupported`);
- the certificate is valid at the package clock: not before its start
  (`certificate.not_yet_valid`) and not at or after its end
  (`certificate.expired`);
- when the certificate has a `keyUsage` extension, it allows
  `digitalSignature` or `nonRepudiation` (`certificate.usage_invalid`).

A missing path fails with `certificate.missing`, an unreadable file or disk with
`certificate.unreadable`, a file that is not a certificate, a key or a PKCS#12
with `certificate.invalid`, a wrong passphrase with
`certificate.passphrase_invalid`, and a PKCS#12 encrypted with an algorithm the
installed OpenSSL no longer loads (such as RC2 under OpenSSL 3 without the
legacy provider) with `certificate.pkcs12_unsupported`; re-export that file with
AES. Every failure is a `CertificateException`
whose `field` and message name only the configuration key
(`efatura.certificates.certificate_path`), never the path, the passphrase or an
OpenSSL message. The OpenSSL error queue is emptied before and after each
operation.

### ICP-CV and the CA bundle

In repository 1 (production) the authority only accepts certificates issued
under the Cabo Verde public key infrastructure, ICP-CV (REPO-LV, p.40). The
Manual says nothing about repositories 2 and 3, and the package does not ship
the ICP-CV certificate authorities. To verify the chain locally, put the CA
certificates in one PEM file on the same disk and set
`efatura.certificates.ca_bundle_path` (`EFATURA_CA_BUNDLE_PATH`); a certificate
the bundle does not trust fails with `certificate.untrusted`. Without a bundle
no chain is checked, and a production certificate outside ICP-CV is only
refused by the authority.

The bundle is the only trust anchor. The system certificate locations
(`/etc/ssl/certs`, `SSL_CERT_DIR`, `SSL_CERT_FILE`) are never consulted, so a
certificate from a public CA is not trusted because the host trusts it. The
bundle may hold several certificates and text between them, but every PEM block
in it must be a certificate that OpenSSL reads; a bundle with no certificate, a
damaged certificate or another kind of block (a private key, for instance) fails
closed with `certificate.untrusted`. The loader copies the bundle into a private
directory under the system temporary directory for OpenSSL and removes it
before it returns.

### Handling the credentials

`Signing\SigningCredentials` holds the OpenSSL certificate and key handles, the
DER certificate, the RFC 4514 issuer name, the decimal serial number and the
validity window. Its `var_dump()`, `print_r()` and `json_encode()` output shows
only the issuer, the serial and the dates, and `serialize()` throws
`DefinitionException` (`definition.credentials_serialization`), so the key never
reaches a queue payload, a cache or a log. Load the credentials where they are
used instead of passing them to a job.

## Signing

`Contracts\XmlSigner::sign(string $xml, SigningCredentials $credentials, SignatureProfile $profile = SignatureProfile::Enveloped): SignedXml`
signs an unsigned `Dfe` or `Event` written by the [XML actions](xml.md). The
container binds it to `Signing\XadesBesXmlSigner`. It parses the XML with the
same hardened parser as the schema validator (no network, no DTD) and refuses:

- a root other than `Dfe` or `Event` in the e-Fatura namespace
  (`signature.unsupported_root`);
- a root whose `Id` is not an IUD with a valid check digit (for `Dfe`) or an
  event ID (for `Event`) (`signature.missing_id`);
- a document that already contains a `ds:Signature`
  (`signature.already_signed`).

After signing, the signer verifies the `SignatureValue` against the certificate
and fails with `signature.failed` when it does not verify. These are
`SignatureException`s. XML the parser refuses fails before any of these checks
with the parser's `SchemaValidationException`: `xml.doctype_forbidden` for a
document type declaration and `xml.malformed` for XML that is not well formed.

`SignedXml` carries the signed XML, the signed `Id`, the profile, the signing
time and the certificate digest, issuer and serial written in the signature.

### Profiles

- `Enveloped` (the default in every action): `ds:Signature` is appended as the
  last child of `Dfe` or `Event`, as `EnvelopedSignature.xsd` allows.
- `InternallyDetached`: the output root is `internally-detached`, without a
  namespace, holding `ds:Signature` followed by the `Dfe` or `Event`, as
  `InternallyDetachedSignature.xsd` defines.

### Structure

The signature follows the official examples of 2024-05-27:

| Element | Value |
| --- | --- |
| Prefixes | `ds:` for `http://www.w3.org/2000/09/xmldsig#`, `xades:` for `http://uri.etsi.org/01903/v1.3.2#` |
| `ds:Signature@Id` | `EmitterPartySignatureId` |
| `CanonicalizationMethod` | `http://www.w3.org/TR/2001/REC-xml-c14n-20010315` (inclusive C14N 1.0) |
| `SignatureMethod` | `http://www.w3.org/2001/04/xmldsig-more#rsa-sha256` |
| First `Reference` | `Id="DataReferenceId"`, `URI="#<Id of the root>"`; transform `enveloped-signature` in the enveloped profile, none in the detached one |
| Second `Reference` | `URI="#SignedPropertiesId"`, `Type="http://uri.etsi.org/01903#SignedProperties"`, transform inclusive C14N |
| `DigestMethod` | `http://www.w3.org/2001/04/xmlenc#sha256` |
| `KeyInfo` | `X509Data/X509Certificate` with the signing certificate only |
| `xades:QualifyingProperties` | `Target="#EmitterPartySignatureId"` inside `ds:Object` |
| `xades:SignedProperties@Id` | `SignedPropertiesId` |
| `SigningTime` | Cabo Verde time as `Y-m-d\TH:i:s`, without an offset |
| `SigningCertificate/Cert` | `CertDigest` (SHA-256 of the DER certificate) and `IssuerSerial` |
| `X509IssuerName` | RFC 4514 (most specific RDN first, comma separated) |
| `X509SerialNumber` | decimal, of any length |
| `DataObjectFormat` | `ObjectReference="#DataReferenceId"`, `MimeType` `text/xml` |

Digests are computed on the nodes of the final document, so the canonical form
of the enveloped root includes the namespace it inherits. The test suite checks
every signature with `robrichards/xmlseclibs`, an implementation independent of
the signer, and checks that changing a signed value breaks it.

The signing time is the package clock in Cabo Verde time, like every other
date-time the package writes: the Manual sets no offset for date-times (§7.21,
p.57) and the examples have none.

### Where the examples disagree

- The enveloped example has only `enveloped-signature` on the data reference
  and gives `SignedProperties` the exclusive C14N URI without its `#`, which is
  not a valid algorithm URI. The other examples and node-efatura use inclusive
  C14N on `SignedProperties`; the package does the same, and keeps
  `enveloped-signature` alone on the data reference in the enveloped profile.
- `99 Event.xml` points its data reference at an IUD instead of the event `Id`;
  the package always references the `Id` of the signed root.
- §9.2 (p.63-64) asks for the xmldsig namespace without prefixes, while figure
  14 (p.71) and every example use `ds:` and `xades:`. The package follows the
  examples.
- Only one example has `SignatureProductionPlace`; the package omits it.

### Differences from node-efatura

- node-efatura writes `X509IssuerName` in Node's order separated by newlines;
  the package writes RFC 4514.
- node-efatura writes `SigningTime` in UTC with milliseconds; the package uses
  Cabo Verde time without an offset, as the examples do.
- node-efatura adds inclusive C14N after `enveloped-signature` on the data
  reference; a correct verifier treats both the same.
- node-efatura signs only in the enveloped profile, reads no PKCS#12 or
  passphrase, and does not check the certificate.

### Schema validation of signed XML

Preparation validates the unsigned XML against the XSD (§9.4, p.64) and then
signs it; it does not validate the signed XML again. `ds:X509SerialNumber` is an
`xs:integer`, and libxml 2.9 refuses values above 64 bits, while ICP-CV serial
numbers can have up to 20 bytes. The signature has a fixed, tested structure,
and the tests validate signed documents against the XSD with short serial
numbers. To validate a signed document yourself on libxml 2.9, expect that
failure on that element alone (see [schema validation](xml.md#schema-validation)).

## Verifying a signature

`Actions\VerifyXmlSignatureAction::handle(string $xml): SignatureVerification`
checks a signed `Dfe` or `Event` the way this package writes it. It parses the
XML with the hardened parser, so XML that is not well formed fails with
`xml.malformed` and a document type declaration (the vector of an XXE attempt)
with `xml.doctype_forbidden`, both as `SchemaValidationException`. It never
signs, stores or sends anything.

It accepts exactly what the signer emits and nothing else:

- one `ds:Signature`, as the last child of `Dfe` or `Event` (enveloped) or as
  the first child of `internally-detached` followed by the `Dfe` or `Event`
  (internally detached);
- inclusive C14N 1.0 for `SignedInfo`, `rsa-sha256` for the signature and
  SHA-256 for every digest;
- two references, in this order: the `Id` of the signed root, then
  `SignedProperties` with its XAdES `Type`, each `Id` held by exactly one
  element of the document;
- `enveloped-signature` and inclusive C14N as the only transforms.

Each reference digest is recomputed, the `SignatureValue` is verified against
the RSA key of the `X509Certificate` in `KeyInfo`, and the `CertDigest` of the
signing certificate must be the SHA-256 of that certificate.

`Signing\SignatureVerification` carries `valid`, the `failures` (a list of
`Enums\SignatureFailure`, each with a stable `code()` such as
`signature.digest_mismatch`), the detected `profile`, and the subject, decimal
serial number and SHA-256 digest of the certificate in `KeyInfo`. Nothing private
is read or returned.

| Failure | When |
| --- | --- |
| `signature.missing` | The document has no `ds:Signature` |
| `signature.multiple` | It has more than one |
| `signature.placement` | The signature is not where either profile puts it |
| `signature.algorithm_unsupported` | A canonicalization, signature, digest or transform algorithm the signer never writes |
| `signature.reference_unexpected` | References other than the two above, or an `Id` that is missing or used twice |
| `signature.digest_mismatch` | A reference digest does not match the signed content or properties |
| `signature.certificate_invalid` | `KeyInfo` holds no readable certificate |
| `signature.key_unsupported` | The certificate key is not RSA |
| `signature.certificate_mismatch` | `CertDigest` is not the SHA-256 of the `KeyInfo` certificate |
| `signature.value_invalid` | `SignatureValue` does not verify against that certificate |

A valid result proves that the document was not changed since it was signed by
the holder of the key in `KeyInfo`. It does not prove who that holder is: the
certificate chain is not verified against ICP-CV or any other authority. Use
`efatura.certificates.ca_bundle_path` when loading your own credentials for that
check (see [ICP-CV and the CA bundle](#icp-cv-and-the-ca-bundle)).

### From the console

```sh
php artisan efatura:validate storage/app/efatura/CV1261002....xml
php artisan efatura:validate signed.xml --profile=detached
php artisan efatura:validate signed.xml --json
```

`efatura:validate` reads the file, refuses a path that does not exist, is not a
regular file, cannot be read or is larger than 5 MiB, and parses it with the
hardened parser. It then validates it against the XSD of the profile and runs
`VerifyXmlSignatureAction`. `--profile` takes `enveloped` or `detached`; without
it the profile follows the root element (`internally-detached` means detached).
The command never submits, stores or signs anything.

It exits with `0` only when both the schema and the signature are valid, and with
`1` otherwise, including every refusal. The output always states that the
certificate chain was not verified against ICP-CV. With `--json` it prints:

```json
{
    "valid": true,
    "profile": "enveloped",
    "schema": {"valid": true, "code": null, "violations": []},
    "signature": {
        "valid": true,
        "failures": [],
        "certificate": {"subject": "CN=...", "serial_number": "1715004", "digest": "..."}
    },
    "trust_chain_verified": false
}
```

`schema.code` is the package code of the failure (`xml.schema_invalid`,
`xml.malformed`, `xml.doctype_forbidden`...), and each violation has its `line`,
`column` and redacted `message`. XML refused by the parser has a `null`
`profile` and `signature`. A refused path or profile prints only
`{"valid": false, "error": "<reason>"}`, with `file_missing`,
`file_not_regular`, `file_unreadable`, `file_too_large` or `profile_invalid`.

---

[Documentation index](README.md) · [Sequences](sequences.md) · [Packaging](packaging.md)
