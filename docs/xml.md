# Identifiers, XML and schema validation

This page covers the official identifiers, the XML the package writes for the
nine DFE and the two fiscal events, and the validation of that XML against the
bundled XSD of 2024-05-27. Signing, packaging and transmission are not part of
this stage.

## Identifiers

`BuildIudAction::handle(IudData $data)` returns the 45-character IUD: `CV`,
the repository code, the issue date as `ymd`, the emitter NIF, the LED with
five digits, the two-digit document type code, the document number with nine
digits, ten random digits and the Luhn check digit of Manual 11, computed over
the 42 digits after `CV`. Without `randomCode` the random digits come from a
`Random\Randomizer` on the secure engine, which the container gives
`BuildIudAction` through a contextual binding, so an application that binds
`Randomizer` for itself never changes it. The issue date is an instant, taken
on its Cabo Verde day. The NIF is always the emitter's, never the transmitter's.

`ParseIudAction::handle(string $iud, string $field = 'iud')` returns the
`IudData` an IUD encodes, and fails with a `ValidationException` on `$field`
when the shape, the date or the check digit is wrong. The same check digit is
verified on every received IUD: an event's `iuds` and
`references[].fiscalDocument.value`. Old document references such as
`1/2021/A/1` are not IUDs and keep their own rule.

`BuildEventIdAction` and `ParseEventIdAction` do the same for the 24-character
event identifier: `CV`, the repository code, the issue instant as `ymdHis` in
Cabo Verde time and a NIF. `EventIdData::$taxId` is neutral; the event XML
expects the transmitter's NIF there, as node-efatura does, until the Manual says
otherwise.

## Writing the XML

`BuildDocumentXmlAction::handle(DocumentData $document, string $iud, Environment $repository, bool $isSpecimen = false)`
and `BuildEventXmlAction::handle(EventData $event, string $eventId, Environment $repository)`
return the XML as a string. Both validate the Data graph first, so a graph built
with `new` fails at its full path before any XML exists, and both refuse an
identifier that names another document or event: the IUD must match the
repository, `header.issueDate`, the emitter NIF, `header.ledCode`, the type and
`header.documentNumber`; the event identifier must match the repository, the
issue instant to the second and the transmitter NIF. A mismatch fails on `iud`
or `eventId`.

The output is XML 1.0 in UTF-8 with the declaration
`<?xml version="1.0" encoding="UTF-8"?>`, compact, in the default namespace
`urn:cv:efatura:xsd:v1.0` without prefixes and without `xsi:schemaLocation`.
Elements follow the order, cardinality and choices of the XSD. The envelope is
`Dfe` (attributes `Version`, `Id` and `DocumentTypeCode`) or `Event`
(`Id`, `Version` and `EventTypeCode`), followed by the body, `Transmission` and
`RepositoryCode`. `<IsSpecimen>true</IsSpecimen>` is written as the first child
of `Dfe` only when `$isSpecimen` is true, in any repository, as the XSD allows.
It is not part of `DocumentData`.

Values:

- Decimals are plain: no exponent, no `+`, no trailing zeros and no lone
  point, and `-0` is written as `0`. The writer never rounds; amounts and
  quantities arrive with at most five decimal places, percentages with three.
- Dates are `Y-m-d`, times `H:i:s` and date-times `Y-m-d\TH:i:s` without an
  offset. Issuance instants are converted to `Atlantic/Cape_Verde` first;
  calendar fields keep the date they were given.
- Booleans are `true` and `false`. Attributes with a default in the XSD
  (`LineTypeCode`, `Discount@ValueType`, `Quantity@IsStandardUnitCode`) are
  always written.
- Text outside the XML 1.0 character set, or invalid UTF-8, fails with a
  `ValidationException` at the field path, without the value in the message.
- An extra field whose name is not an XML 1.0 name, or whose namespace the
  document cannot declare, fails at `footer.extraFields.N.name` or
  `footer.extraFields.N.namespace`.

Fields the XSD requires but the staged Data allows to be absent are required
here: `header.serie`, `header.documentNumber`, `emission`,
`emission.transmitterTaxId` and `emission.software` fail with
`validation.xml_required`. The action never fills the series from the LED or
the transmission from the configuration. `IssueTime` is always written, also in
the Off mode, because the published XSD requires it. In an UDN event, `Year` is
written only when `numberRange.year` is given; the XSD leaves it optional.

An event's `EmitterTaxId` is the NIF of the DFE emitter, never the
transmitter's, as node-efatura settled in #72.

A DTE has no `Totals`. FDC and UDN share one serializer: FDC lists one or more
`IUD`, UDN the number range.

## Schema validation

`Contracts\SchemaValidator::validate(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped)`
validates a document or event against the bundled XSD of the given signature
profile (`Enveloped` or `InternallyDetached`). The container binds it to
`Xml\LibxmlSchemaValidator`, which:

- refuses any document type declaration with `xml.doctype_forbidden`, before
  parsing when it can see it and after parsing in other encodings, so external
  and recursive entities are never expanded. When a document in another
  encoding fails to parse, a recovering parse looks for the declaration, so a
  recursive entity is refused as a declaration and not reported as malformed;
- parses with `LIBXML_NONET` only, and reports malformed XML as
  `xml.malformed`;
- resolves the entry XSD through `OfficialArtifacts::xsdEntry()` and every
  `include` and `import` through an entity loader that accepts only files listed
  in `resources/official-artifacts.json`, after checking their size and
  checksum and refusing symbolic links. Any other request, network or file,
  fails with `xml.external_resource`; a listed file whose bytes changed fails
  with the `OfficialArtifactException` of the manifest check, so validation
  never runs against a modified schema;
- fails with `OfficialArtifactException` (`artifacts.invalid_schema`) when a
  listed schema matches the manifest but does not compile, since the fault is
  in the bundle and not in the document;
- reports schema failures as `xml.schema_invalid`, and so does a valid element
  that is not a root of the profile: `Dfe` or `Event` in the e-Fatura namespace
  for `Enveloped`, `internally-detached` without a namespace for
  `InternallyDetached`.

Every failure is a `SchemaValidationException` in the `EfaturaException`
hierarchy. Its `violations` are `Xml\SchemaViolation` values with the line,
column, libxml level and a redacted message: the element and attribute names
and the XSD facet stay, quoted literals are cut to `'…'`, so no document value
and no resource path leave the validator.

Each call saves and restores the libxml state: the internal error setting, the
external entity loader (also when validation fails) and the error buffer. Errors
the caller left pending stay in the buffer, and the validator only reports the
errors of its own call, so two validations never share errors. When the caller
had no pending error, the buffer is cleared after the call. When it had some,
the validator cannot remove its own errors without removing the caller's, so
they stay after the caller's errors. Those are libxml's raw errors, not the
redacted violations, so a caller that keeps errors pending across a validation
should clear the buffer itself before logging it.

The test suite validates the minimal and maximal graph of every document type,
an invoice in the Online, Offline and Off modes, a specimen in each repository,
FDC with one and several IUDs, UDN with and without `Year`, and the official
examples. `5 CreditNote.xml` and `8 ReturnNote.xml` carry a 46-digit
`ds:X509SerialNumber`; libxml 2.9 rejects it as an `xs:integer` while libxml
2.15 accepts it. The suite validates both examples with the signature removed,
and accepts the full examples only when they pass or fail on that element alone.

## Deliberate differences from node-efatura

- The series is never derived from the LED or the configuration (node #74).
- `IssueTime` is written in the Off mode too (node #69 omits it); the XSD
  requires it.
- The document number is read from the header and compared with the IUD rather
  than read from the IUD.
- Received IUDs must carry a valid check digit.
