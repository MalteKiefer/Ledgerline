# Finance module foundation

## Status and cutover boundary

The Finance module foundation is additive. Its code lives below
`App\Modules\Finance`, and its schema uses tables prefixed with
`finance_document_`.

**There is no production cutover in this foundation.** Existing quote, project,
invoice, recurring-invoice, reporting, tax, PDF, mail, reminder, dunning,
discount, and storno routes still use the legacy Finance implementation. The
only `finance-v2` HTTP route is the authenticated module health check. A later
cutover plan must explicitly route production workflows to the new module after
their application adapters and parity checks exist.

`DocumentRevisionRepository` has a production Eloquent binding. The
`DocumentRenderer` and `DocumentStorage` ports deliberately have no production
binding yet, so `PublishDocumentRevision` is not a resolvable production
workflow until a downstream quote or invoice plan supplies both adapters.

## Namespace and dependency boundaries

| Namespace | Responsibility | Allowed dependencies |
| --- | --- | --- |
| `App\Modules\Finance\Domain` | Exact value objects, document calculation, and reusable workflow rules | PHP standard library and other Finance Domain classes only |
| `App\Modules\Finance\Application` | Commands, DTOs, and ports that orchestrate domain behavior | Finance Domain and abstract ports; no HTTP or Eloquent dependency |
| `App\Modules\Finance\Infrastructure` | Eloquent persistence and database transactions | Application ports, Domain values, Laravel/Eloquent/Auth/DB |
| `App\Modules\Finance\Http` | Module HTTP routes, controllers, and resources | Application/module surfaces and Laravel HTTP |
| `App\Modules\Finance\FinanceServiceProvider` | Route loading and adapter bindings | Laravel container plus module interfaces/adapters |

The test suite mechanically rejects these dependencies below `Domain/`:
`Illuminate\Http`, `Illuminate\Database\Eloquent`,
`Illuminate\Support\Facades`, and `Symfony\Component\HttpFoundation`. Domain
and Application money arithmetic must not use floating point.

The provider is registered in `bootstrap/providers.php`. It exposes:

- Route: `GET /api/v1/finance-v2/health`
- Route name: `api.finance-v2.health`
- Middleware: `api`, `auth:sanctum`, `abilities:device`, token-IP update,
  two-factor enrollment, `module:finance`, and `throttle:120,1`
- Exact response: `{"module":"finance","schemaVersion":1}`
- Version marker: `FinanceModule::SCHEMA_VERSION === 1`

The health route is an availability and boundary probe only. It does not
return a legacy Finance snapshot and does not activate a new document workflow.

## Exact numeric contracts

### `Money`

`Money` is a readonly integer-minor-unit value. Its public API is:

```php
Money::fromDecimal(string $amount, string $currency): Money
Money::fromMinor(int $minor, string $currency): Money
$money->minor(): int
$money->currency(): string
$money->add(Money $other): Money
$money->subtract(Money $other): Money
```

`fromDecimal` accepts only `-?\d+(\.\d{1,2})?`: a dot is the only decimal
separator; exponent notation, a leading plus, whitespace, and more than two
fraction digits are invalid. The stored scale is 2. The supported database
range is signed `decimal(14,2)`, or minor units from
`-99_999_999_999_999` through `99_999_999_999_999`.

Currency input is normalized to uppercase and must then match `[A-Z]{3}`.
Addition and subtraction require identical currencies and recheck the supported
range. Invalid input or arithmetic throws `InvalidMoney`.

### `DecimalQuantity`

`DecimalQuantity::fromString(string)` accepts only
`-?\d+(\.\d{1,4})?`. It exposes `scaled(): int`, where the fixed scale is 4:
`1` becomes `10000`, `1.5` becomes `15000`, and `0.0001` becomes `1`.
Comma decimals, exponent notation, a leading plus, whitespace, and more than
four fraction digits are invalid. The scaled value must fit the platform PHP
integer range; invalid input throws `InvalidQuantity`.

Canonical request and persistence adapters must construct these values from
strings or integers. They must never pass client floating-point values into
authoritative calculations.

### Rounding

`Rounding::halfAwayFromZero(int $numerator, int $denominator): int` is the sole
rounding primitive. The denominator must be positive. It uses integer quotient
and remainder arithmetic; exact halves round away from zero (`0.5 -> 1`,
`-0.5 -> -1`). No intermediate float is permitted.

## Authoritative document calculator

`DocumentCalculator::calculate(array $lines, Discount $discount):
DocumentTotals` consumes a non-empty `list<DocumentLine>`. Each line contains:

- `description: string`
- `quantity: DecimalQuantity` at scale 4
- `unitPrice: Money` in minor units
- `taxRateBasisPoints: int` from `0` through `10000`

Every line and the discount must use one currency. Negative line amounts are
supported. A line net is calculated as
`halfAwayFromZero(quantity_scaled * unit_price_minor / 10000)` with checked,
integer-only arithmetic.

Discount inputs are created with `Discount::none(currency)`,
`Discount::percentBasisPoints(basisPoints, currency)`, or
`Discount::fixed(Money)`. Percentage basis points must be in `0..10000`.
For a non-negative raw document net, the calculated discount must be in the
inclusive range from zero through that raw net. If the raw document net is
negative, only a zero discount is accepted. Negative discounts are always
rejected.

Discount minor units are distributed proportionally by raw line net. Any
remainder cents go only to non-zero lines with the remainder's sign, ordered by
tax rate and then original line position. Discounted net is grouped by tax rate,
VAT is rounded once per group with the shared rounding rule, and breakdowns are
returned in ascending numeric tax-rate order.

The readonly `DocumentTotals` output contains:

- `net: Money` — net after discount
- `vat: Money`
- `gross: Money`
- `discount: Money`
- `taxBreakdowns: list<TaxBreakdown>`, each with rate, net, VAT, and gross

`matchesControlTotals(?Money $net, ?Money $vat, ?Money $gross): bool` compares
every supplied client control value exactly by minor units and currency. `null`
means that control is omitted. Control totals never replace calculated totals.

## Workflow contract

`StateMachine` is a readonly, domain-neutral transition map. Construct it from
`array<string,list<string>>`, then use:

```php
$machine->can(string $from, string $to): bool;
$machine->assertCan(string $from, string $to): void;
```

State names are preserved exactly; they are not trimmed, normalized, or given
quote/invoice semantics by this shared class. An empty map is valid and produces
a machine that allows no transitions. Malformed entries—an empty or non-string
source/target state, or a non-array target list—throw
`InvalidArgumentException`. A disallowed, reverse, self, or unknown transition
causes `assertCan` to throw `InvalidTransition`, whose stable public properties
are `from`, `to`, and string code `invalid_transition`.

## Schema ownership and integrity

The migration creates four additive tables and does not alter legacy Finance
tables:

| Table | Ownership and purpose | Key integrity rules |
| --- | --- | --- |
| `finance_document_series` | Root aggregate, directly owned by `user_id` | unique `(user_id, uuid)`; unique `(user_id, source_type, source_id)`; source type and ID are both null or both present |
| `finance_document_revisions` | Immutable published snapshots and totals | positive `revision_number`; unique `(document_series_id, revision_number)`; previous revision must be a different row in the same series |
| `finance_document_activities` | Append-only audit events | owner and series must match; optional revision must match the same owner and series |
| `finance_document_notes` | Internal/customer notes | owner and series must match; optional revision must match; visibility is `internal` or `customer` |

Every table carries `user_id`. All records use the existing `OwnsUserData`
scope, and child access begins from an owner-scoped series. Composite foreign
keys prevent a child or revision reference from crossing owner or series
boundaries even if an ID is guessed.

Revisions store `snapshot` as JSON; `net_minor`, `vat_minor`, and `gross_minor`
as signed bigint minor units; `currency` as three characters; and nullable,
server-owned `pdf_path`, `pdf_sha256`, and `published_at`. Activities and notes
have owner/timestamp and series/timestamp indexes; series have an
owner/type/status index; revision history has series/number uniqueness and a
series/creation index.

Deleting an owner cascades the complete aggregate. Deleting a series directly
while revisions exist is rejected. PostgreSQL uses deferred `NO ACTION` edges
where necessary so the owner-wide cascade can finish without weakening direct
series/revision guards. SQLite has equivalent tested behavior. Direct deletion
of a referenced revision is rejected. The database prevents direct
self-reference but does not claim chronological ordering or prevent a longer
multi-row previous-revision cycle; application services create the chain in
order.

## Revision immutability

A draft `DocumentRevisionRecord` may be updated. The first atomic publication
write sets status, PDF path, PDF hash, and publication time. After the persisted
`published_at` is non-null, Record API update and delete operations throw
`PublishedRevisionMutation`, including model events, quiet writes, and guarded
Eloquent bulk helpers. PDF metadata and snapshot replacement are therefore
blocked after publication.

`DocumentActivityRecord` is append-only: Record API update or delete operations
always throw `PublishedRevisionMutation`. Notes are not covered by this
append-only rule.

Raw connection-level SQL and database-owned cascades sit below the Record API
and do not throw the Eloquent exception. Application code must mutate these
tables through the module repository/records; direct SQL is reserved for
migrations, integrity repair, and database-owned lifecycle operations.

## Revision application interfaces

### Creation

`CreateDocumentRevision::handle(CreateRevisionData): DocumentRevisionId`
accepts:

- owner-scoped `seriesUuid`
- caller metadata snapshot
- `list<DocumentLine>`
- `Discount`
- optional change reason

The command recalculates totals. It replaces caller-provided `lines` and
`totals` snapshot keys with authoritative scaled/integer representations,
rejects floats and non-JSON scalar/object values, recursively sorts associative
keys, preserves list order, and hashes the canonical JSON with SHA-256.

The repository locks the series, assigns revision numbers starting at 1, links
the immediate predecessor, retries only the series/revision-number unique
collision (up to three attempts), persists the calculated control columns, and
appends `revision.created` with the snapshot digest. `DocumentRevisionId` only
accepts a positive integer.

### Publication

`PublishDocumentRevision::handle(DocumentRevisionId): PublishedRevision`
locks in `series -> revision` order and is idempotent: an already published
revision returns its stored result without another render, object write, or
activity. The result contains revision ID, revision number, safe relative PDF
path, lowercase SHA-256 digest, and immutable publication time.

The public ports are:

```php
interface DocumentRevisionRepository
{
    public function create(
        CreateRevisionData $data,
        DocumentTotals $totals,
        array $canonicalSnapshot,
        string $snapshotSha256,
    ): DocumentRevisionId;

    public function publish(
        DocumentRevisionId $id,
        Closure $storePdf,
    ): PublishedRevision;
}

interface DocumentRenderer
{
    public function render(array $snapshot): string;
}

interface DocumentStorage
{
    public function putPdf(
        string $seriesUuid,
        string $bytes,
        string $ownershipToken,
    ): StoredDocument;

    public function delete(string $ownershipToken): void;
}
```

Renderers receive the canonical authoritative snapshot and return PDF bytes.
Storage must create a new, non-deduplicated object bound to the supplied random
256-bit ownership capability before it becomes visible. `StoredDocument`
accepts only a safe relative `.pdf` path and a lowercase 64-character SHA-256.
The command verifies that digest against the rendered bytes.

If a post-write transaction step fails, publication uses the ownership token to
delete only that attempted object. Cleanup or logging failures never replace the
primary publication exception. Storage and the database are not one atomic
transaction; a production adapter must support later operational retry or orphan
reconciliation for a persistent delete outage.

## Verification record

Run from `backend` on 2026-08-28:

```text
php artisan test tests/Feature/FinanceModule tests/Unit/Modules/Finance tests/Feature/FinanceQuoteTest.php tests/Feature/FinanceProjectPlanTest.php tests/Feature/FinanceRecurringTest.php tests/Feature/FinanceReportsTest.php tests/Feature/TaxReportsTest.php tests/Feature/InvoiceDiscountTest.php tests/Feature/InvoiceDunTest.php tests/Feature/InvoiceEmailTest.php tests/Feature/InvoiceReminderTest.php tests/Feature/InvoiceStornoTest.php tests/Feature/InvoiceVersionPdfTest.php
```

With the repository's local-development storage setting `FILES_DISK=local`:
**PASS — 202 tests, 882 assertions**.

The same command without `FILES_DISK=local` produced one environment-only
failure in the unchanged legacy
`TaxReportsTest::test_small_business_flag_persists_via_web_and_api_company`:
the default `files` S3 disk had no `FILES_S3_BUCKET`/`AWS_BUCKET`, so Flysystem
received a null bucket. The failing test, controller, `BlobStore`, filesystem
configuration, and files configuration are unchanged from foundation base
`a661994c`; the only PHPUnit configuration change is Task 1's deterministic
test-only `APP_KEY`. This is a Windows/local baseline configuration issue, not a
Finance module regression.

```text
vendor/bin/pint --test app/Modules/Finance tests/Feature/FinanceModule tests/Unit/Modules/Finance
PASS

vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G
PASS — 0 errors
```

The first PHPStan run exposed a real module issue: discount allocation promised
`list<int>` but did not explicitly restore list keys after indexed updates.
Returning `array_values($allocations)` fixes the interface guarantee without
changing calculator values; the focused calculator suite passed with 20 tests
and 137 assertions before the complete gates were rerun.

## Downstream obligations

Downstream quote, project, invoice/payment, recurring, import, migration,
frontend-cutover, legacy-removal, and release plans must keep this boundary:

- use integer minor units and scaled quantities end to end;
- treat calculated totals and canonical snapshots as authoritative;
- use named workflow commands rather than generic status CRUD;
- keep every aggregate owner-scoped;
- preserve published revision and PDF identity immutability;
- provide production renderer/storage adapters before exposing publication;
- do not switch production routes until migration control totals and workflow
  parity are proven by the later cutover plans.
