# Project Plan Task 13 — legacy mapping and cutover gates report

Date: 2026-08-30

## Outcome

Implemented a deterministic, read-only legacy-to-v2 mapping layer for
`finance_projects` under `App\Modules\Finance\Infrastructure\Compatibility`,
plus `docs/finance/projects-documents-notes.md` documenting the schema,
DTOs/commands/queries, route contract, source adapters, the mapper itself,
and the nine cutover gates. No bulk migration, schema write, or legacy
mutation occurs anywhere in this task — `LegacyProjectMapper` only reads the
legacy aggregate and cross-module evidence pointers and returns a plan plus
diagnostics.

## Exact expense parsing

`finance_projects.expenses` has no fixed row shape (a free-form
hand-typed JSON array validated only as `array|max:5000`), so the parser
tolerates missing keys but never trusts `json_decode()`'s float cast for the
amount. `LegacyJsonCursor`/`LegacyJsonNumber` are a small hand-rolled JSON
tokenizer built solely to capture a numeric lexeme as its exact source
substring; every other JSON value (strings with escapes, booleans, null,
objects, arrays) is otherwise standard. `LegacyProjectExpenseParser` accepts
an amount as either a bare JSON number or a numeric-shaped JSON string
(both routed to `Money::fromDecimal` as raw text), rejects exponent
notation, more than two fraction digits, a non-array top level, a
non-object row (including a JSON object whose only keys are numeric strings,
which PHP would otherwise silently coerce to a list-shaped array), a
missing/zero amount, and a currency that disagrees with the project's
currency — each as a distinct `expenses_*` error code. Direction is read
from `direction`/`type` when present; otherwise a positive amount is `out`
and a negative one is `in`. Unknown keys are retained verbatim (recursively,
for nested values) under `legacy_metadata`.

## Idempotent project mapping

`LegacyProjectMapper::map(FinanceProject, string $defaultCurrency = 'EUR')`
uses `source_type=legacy.finance_project` plus the legacy id as its
deterministic identity — mapping the same project twice yields the same
plan (proven by a dedicated test). It produces:

- Project attributes: status/kind validated against the v2 enums (not just
  copied), exact `budget_minor` via `Money::fromDecimal`, partner/quote
  references as opaque strings, `parent_source_id`, and archive state. A
  quote reference is deliberately left as `legacy-quote-unresolved:{id}` —
  an unresolved pinned source, non-blocking, until quote migration resolves
  the concrete immutable revision; this plan does not reinterpret quote
  state.
- The mutable `note` field mapped once into an initial internal
  `project_note` (blank/whitespace-only note maps to none).
- Tasks mapped to work items via `WorkItemStatus`/`DecimalQuantity`; a
  milestone carrying an estimate is a **blocking** diagnostic
  (`task_milestone_with_estimate`), matching the locked domain rule that a
  milestone accepts no estimated quantity.
- Time entries mapped via `DecimalQuantity`/`Money`; zero or unparseable
  hours and an unparseable rate are blocking, a missing rate is a
  non-blocking diagnostic (`time_entry_rate_missing`), and an
  already-invoiced entry keeps an opaque `legacy-invoice:{id}` target with a
  non-blocking `time_entry_invoice_unresolved` diagnostic — this plan does
  not resolve or renumber the legacy invoice.
- Ledger entries from the expense parser.
- Document-link candidates scanned from `FileEntry`, `GalleryPhoto`,
  `FinanceReceipt`, and `BankTransaction` rows pointing at the project. A
  pointer owned by a different user than the project is a **blocking**
  diagnostic (`document_link_cross_owner`) rather than a silently dropped
  row.

Legacy `finance_projects` has no `currency` column at all (confirmed against
`2026_12_03_091002_create_finance_projects.php` and
`2027_03_03_100000_create_project_planning.php`), so the mapper takes the
owner's default currency as a parameter; every mapped amount/rate is
expressed in it.

## A real bug this task's phpstan gate caught

`getRawOriginal()` returns `mixed`, and every legacy decimal column this
mapper reads (`budget_net`, `estimate_hours`, `hours`, `hourly_rate`) is
`decimal(_, 2)`. On PostgreSQL/MySQL that hydrates as exact source text, but
SQLite has no DECIMAL storage class and returns a PHP float instead. The
initial blind `(string) $rawValue` cast happened to work by coincidence in
the first test run; PHPStan's `cast.string` rule rejected the pattern
outright. The fix is a small `rawText()` narrower that treats a float
explicitly — formatting it back to exactly two fraction digits (the
column's own declared scale) rather than trusting the float's full,
imprecise binary expansion — which is also the more correct behavior: a
silent blind cast would have serialized SQLite's float noise (e.g.
`1234.5600000000004`) straight into `Money::fromDecimal` and failed
non-obviously on some values.

## Scope limitation (documented, not silently dropped)

The document-link mapper test exercises `BankTransaction` and
`FinanceReceipt` end to end (including the cross-owner blocking case), but
not `FileEntry`/`GalleryPhoto` — their `files`/`gallery_photos` tables carry
many additive migrations (team-scoped legacy history, encryption, search
indexing) and constructing a minimal valid row costs materially more than
the other two evidence kinds for the same coverage value. The mapper code
itself queries all four kinds identically; only the test fixture is
narrower. This is called out here rather than left implicit.

## Verification

- `LegacyProjectMapperTest`: **13/13 passed, 96 assertions**.
- `LegacyProjectMapperTest` + `FinanceProjectPlanTest` + `FinanceRelationalTest`
  + `FinanceScopeTest`: **55/55 passed, 370 assertions**.
- `vendor/bin/pint --test` on the new Compatibility files and
  `tests/Feature/FinanceModule/Projects`: **passed**.
- `vendor/bin/phpstan analyse app/Modules/Finance/Infrastructure/Compatibility --memory-limit=1G`: **0 errors**.

## Scope hygiene

- Only the Task 13 file list (four production classes plus the exception
  and number/cursor helper classes the plan's `LegacyProjectExpenseParser`
  step implies, one test file, and the docs file) is included.
- No schema, HTTP, provider-binding, or bulk-migration code is included —
  `LegacyProjectMapper` performs no writes.
- No push, tag, deployment, or integration action was performed.
