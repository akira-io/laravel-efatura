# Document numbers and sequences

This page covers how the package reserves the number of a document, where the
counters live, and what happens to a number when issuance fails after the
reservation. Preparation, which calls the reservation, is described in
[builders and configuration](builders.md#preparation).

## Scope

Manual 11 numbers documents without gaps per emitter NIF, year, LED and
document type (DN-SEQ, p.34), restarts at 1 every year since 2022 (DN-FY1,
p.34), and treats (NIF, year, LED, type, number) as unique (ID-UK2, p.33). The
series is not part of the scope and not part of the IUD.

`Sequence\SequenceScope` holds that key: the emitter NIF (never the
transmitter's), the year, the LED code and the `DocumentType`.
`SequenceScope::forDocument($document)` reads them from a validated document:
`emitter.taxId.value`, the year of `header.issueDate` in Cabo Verde time,
`header.ledCode` and `type()`. An invoice issued at `00:30 UTC` on 1 January is
still numbered in the previous year, because it was issued on 31 December in
Cabo Verde. A scope built by hand with a NIF that is not a Cabo Verde NIF, a
year outside 2021 to 2099 or a LED outside 1 to 99999 fails with
`DefinitionException` (`definition.sequence_scope`): scopes are built from
validated data, so this is a programming error and not input validation.

## Reserving a number

`Contracts\SequenceStore::next(SequenceScope $scope): int` returns the next
number of the scope, starting at 1. The container binds it to
`Sequence\DatabaseSequenceStore`, which runs one short transaction on the
connection `efatura.database.connection` (null inherits `database.default`)
and the table `efatura.database.sequences_table`:

1. insert the scope row with `current_number = 0`, ignoring the conflict when
   it already exists;
2. increment `current_number` for the scope and set `updated_at`;
3. read `current_number` back and return it.

The write comes first, so the database serializes concurrent reservations of
the same scope on the row (or, in SQLite, on the file). No `SELECT ... FOR
UPDATE` is used: it does not lock a row that does not exist yet, and it is a
no-op in SQLite, which is how node-efatura handed out duplicates (#12). The same
statements run on SQLite, MySQL and PostgreSQL.

The counter is an unsigned big integer. A reservation that would go past
999 999 999 (`Fiscal::MAX_DOCUMENT_NUMBER`, the `stDocumentNumber` bound) fails
with `SequenceException` `sequence.exhausted`, and the transaction rolls back,
so the stored value stays at 999 999 999.

### Contention and outages

When the store owns the transaction it retries a reservation the database
reports as a deadlock, a lock timeout or `database is locked`, up to
`DatabaseSequenceStore::ATTEMPTS` (5) times. Any other database failure, or a
lock that outlasts the attempts, fails with `SequenceException`
`sequence.unavailable`, with `retryable` set to true, the scope (emitter NIF,
fiscal year, LED and document type code) in `context` and the database
exception as `previous`. The message is the code, never the SQL. A failed
reservation rolls back, so it neither consumes nor skips a number.

When `next()` runs inside a transaction of the caller, the reservation is a
savepoint of that transaction. A lock failure then fails at once with
`sequence.unavailable` instead of retrying, because the database may already
have rolled back the caller's whole transaction: retry the caller's unit of
work, not the reservation. A caller that rolls back its own transaction also
rolls back the number.

### SQLite

SQLite has one writer per database file. A connection that finds the file
locked waits up to its `busy_timeout` (in milliseconds, set on the connection in
`config/database.php`), polling rather than queueing, so under heavy contention
one process can be passed over for the whole timeout. Set a `busy_timeout`
(5000 is a reasonable start) on any SQLite connection that several workers use
for numbering; without one, PDO waits 60 seconds. For many processes issuing
documents at once, prefer MySQL or PostgreSQL, where the reservation only locks
the row of its scope.

The test suite runs eight processes that reserve 50 numbers each from one SQLite
file and expects exactly the numbers 1 to 400, without repeats or gaps.

## The migration

The table is created by a publishable migration, never run from the package:

```sh
php artisan efatura:install
# or
php artisan vendor:publish --tag=efatura-migrations
```

`efatura:install` copies `create_efatura_sequences_table` into
`database/migrations` unless a migration with that name is already there. The
migration reads `efatura.database.connection` and
`efatura.database.sequences_table` when it runs, so set them first. The table
has `emitter_tax_id` (`char(9)`), `fiscal_year`, `led_code`,
`document_type_code`, `current_number` (default 0) and timestamps, with the
four scope columns as its primary key. Then run `php artisan migrate`.

## Gaps, UDN and resuming

A reserved number is consumed. `PrepareDocumentAction` loads the signing
credentials before it reserves, so a wrong certificate configuration never
consumes a number; any failure after the reservation (XML, schema, signature,
packaging) throws `PreparationException`
(`preparation.failed_after_allocation`) with the `documentNumber`, the `iud`
and the scope in `context`. The next call receives the next number. Close the
gap with an UDN event for that number (see [identifiers, XML and schema
validation](xml.md)), or resume the same document.

To resume, set `header.documentNumber` to the reserved number and prepare the
document again: a document that already carries a number reserves nothing and
does not read the counter. The same applies to numbers allocated outside the
package. The authority checks DN-SEQ later, so the package does not compare
given numbers with the counter.

## Tests

`Sequence\InMemorySequenceStore` implements the contract in memory with the same
scope and exhaustion rules, plus `current()` and `continueAfter()` to set up a
counter. Bind it in a test container:

```php
$this->app->instance(SequenceStore::class, new InMemorySequenceStore);
```

It is not shared between processes or requests; never use it to issue real
documents.

---

[Documentation index](README.md) · [Signing](signing.md) · [Packaging](packaging.md)
