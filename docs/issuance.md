# Issuance

`IssueDocumentAction` and `IssueEventAction` take a validated document or event
to the platform answer in one call:

```php
$issued = resolve(IssueDocumentAction::class)->handle($document);

$issued->authorized();               // the entry was accepted
$issued->result->authorizationCode;  // AuthorizationCode of figure 13
$issued->result->messages;           // RemoteMessage list of a rejection
$issued->prepared;                   // PreparedDocument: number, IUD, XML, signature, ZIP
```

`IssueDocumentAction::handle($document, $isSpecimen, $profile, $iud)` and
`IssueEventAction::handle($event, $profile)` run synchronously, as the platform
does (§5.4, p.12). Call them from a job of the application when the request
must not wait; the package ships no job of its own yet.

## Flow

1. `PrepareDocumentAction` (or `PrepareEventAction`): emission context,
   credentials, number and IUD, XML, schema validation, signature and ZIP. No
   network. A reservation never happens inside a transaction on the sequence
   connection (`sequence.inside_transaction`), so no request ever runs inside it
   either. See [builders](builders.md#preparation) and [packaging](packaging.md).
2. `DocumentPrepared` (or `EventPrepared`) is dispatched synchronously, before any
   request. Store the `PreparedDocument` there: it is what a retry or a
   reconciliation needs. An exception from a listener aborts the issuance with
   `IssuanceException` (`issuance.aborted_before_submission`, with the number,
   IUD and scope in `context` and the listener failure as `previous`), and
   nothing is sent.
3. `SubmitPreparedDocumentAction` (or `SubmitPreparedEventAction`) sends the
   prepared archive through the configured [transport](transport.md).
4. The answer dispatches `DocumentAuthorized` or `DocumentRejected` (`EventAuthorized`
   or `EventRejected`) with the `EntryResult`. A `TransportException` dispatches
   `DocumentSubmissionFailed` (`EventSubmissionFailed`) with the failure and
   whether it is retryable, then propagates. From here on a listener exception is
   handed to the `ExceptionHandler::report()` of the application and the result is
   still returned: a listener can never undo an authorization the platform granted.

Every event is `final readonly` and carries the prepared artifact, the entry
result or the failure code; none carries a credential.

## Outcomes

| Outcome | Meaning | What to do |
| --- | --- | --- |
| Authorized | The PE stored the document with its authorization code | Keep the signed XML, the authorization code and the signature time stamp |
| Rejected | The PE validated and refused it; it stored nothing (only authorized documents occupy ID-UK1/ID-UK2, p.33) | Correct the document and prepare it again with the same number and IUD (`handle($fixed, iud: $issued->prepared->iud)`), or close the number with an UDN event when the document is abandoned |
| `transport.connection_failed` | The request never left (DNS, refused, connect timeout, TLS handshake) | Resend the same `PreparedDocument` with `SubmitPreparedDocumentAction` |
| `transport.rate_limited` | 429 | Resend the same `PreparedDocument` later |
| `transport.unauthorized`, `transport.http_error`, `transport.payload_too_large`, `transport.missing_credentials` | Refused before processing, or a configuration problem | Fix the credentials or the configuration, then resend the same `PreparedDocument` |
| `transport.outcome_unknown`, `transport.unexpected_response`, `transport.response_too_large` | The request may have been processed | Reconcile before anything else |

Never prepare a document again with a new number after a failure: the number and
the IUD are reserved for it.

## Reconciliation

The Manual offers no idempotency key; the IUD is unique in the platform (ID-UK1,
p.33). After an unknown outcome, look the IUD up:

```php
$reconciliation = resolve(ReconcileDocumentAction::class)->handle($prepared->iud);

if ($reconciliation->authorized()) {
    // $reconciliation->xml holds the document the PE stored
} else {
    resolve(SubmitPreparedDocumentAction::class)->handle($prepared);
}
```

`ReconcileDocumentAction` sends `GET dfe/xml/{IUD}` (scope `cv_ef_dfe_read_iud`,
§10.3, p.70). Found means authorized; not found means the PE never stored it, so
send the same `PreparedDocument` again, with the same bytes. A lookup that fails
is retried as a read (see [retries](transport.md#retries)) and otherwise throws; it
never answers not found by itself.

Events have no documented read (§10.4, only the `cv_ef_event_read` scope): after
an unknown outcome, the only remedy is to resend the same `PreparedEvent`. The
Manual does not say how the PE answers an event it already holds, so a rejection
of that resend may mean the first one succeeded. Keep both answers, and check the
target documents in the platform portal before acting on it.

## Time limits and contingency

In online mode the issue date and time may be up to 24 hours behind and one hour
ahead of the platform clock (IDT-IMO-LE/GE, p.35). A resend or a reconciliation
must therefore conclude within 24 hours of the issue time. After that, the
document must be issued in contingency (up to seven days, IDT-IMC-GE, p.35), which
the package will support in a later release. [Readiness](readiness.md) compares
the local clock with the platform before you start.

## UDN

An unused document number event closes a number that will never be authorized:
a document abandoned after a rejection, or a number reserved by a preparation
that failed (`preparation.failed_after_allocation`, see
[sequences](sequences.md#gaps-udn-and-resuming)). Never issue an UDN for a number
whose outcome is unknown: reconcile it first, because the PE may hold it.

---

[Documentation index](README.md) · [Transport](transport.md) · [Readiness](readiness.md)
