# Invoice Task 2 Report

## Status

Task 2 is implemented and verified test-first. The change is additive and creates the owner-scoped invoice, invoice-number sequence, delivery, and idempotency schema without changing legacy Finance runtime tables or the Foundation migration.

## Files

- `backend/database/migrations/2026_08_28_110000_create_finance_invoices.php`
- `backend/tests/Feature/FinanceModule/InvoiceSchemaTest.php`
- `.superpowers/sdd/invoice-task-2-report.md` (required report only)

No Provider, Quote, Project, Payment Task 3, or Recurring files were changed or staged by this task.

## Implemented contract

- `finance_invoices` keeps workflow, numbering, optional compatibility/source links, exact signed `BIGINT` balance projections, optimistic version, and cancellation relation. Customer, line, total, text, and PDF data remain revision-owned.
- Invoice-to-series, invoice-to-current-revision, cancellation, and delivery-to-invoice/revision relations are composite owner-safe foreign keys. History-bearing references use `NO ACTION`, PostgreSQL deferrability, and owner deletion uses direct cascades.
- Optional invoice numbers, source identities, and cancellation targets use PostgreSQL/SQLite-compatible partial unique indexes.
- Number/source all-or-none groups, valid invoice/delivery/idempotency states, non-negative sequence/attempt counters, lowercase SHA-256 shape, self-cancellation, and response-status bounds are checked in PostgreSQL and by equivalent SQLite insert/update triggers.
- The new migration additionally enforces the Foundation revision currency boundary as exactly three uppercase letters. `down()` removes this additive constraint/trigger before returning the Foundation schema to its prior state.
- Delivery rows pin the immutable revision that was sent and retain that relation if the invoice later points at a newer revision.
- Idempotency records persist both key and canonical-request hashes plus replay response data; uniqueness is owner- and operation-scoped.

## TDD evidence

- Baseline: `DocumentCoreSchemaTest` passed with 15 tests / 58 assertions before implementation.
- Initial RED: `InvoiceSchemaTest` failed because all four new tables/migration were absent.
- Initial GREEN: 13 schema tests / 90 assertions passed after the minimal migration.
- Currency RED: SQLite accepted invalid `EU`, proving the missing revision-currency guard.
- Currency GREEN: the complete schema suite passed after the additive PostgreSQL check / SQLite triggers.
- SHA-256 RED/GREEN: correctly sized non-hex values were initially accepted; `VARCHAR(64)` plus PostgreSQL regex / SQLite trigger checks now reject them.
- Mutation check: removing the cancellation owner foreign key made the dedicated cross-owner cancellation test fail; restoring it made the test pass.
- Mutation check: removing delivery idempotency uniqueness made the dedicated delivery test fail; restoring it made the test pass.

## Final verification

- `php artisan test tests/Feature/FinanceModule/InvoiceSchemaTest.php tests/Feature/FinanceModule/DocumentCoreSchemaTest.php`
  - PASS: 31 tests, 166 assertions.
- `vendor/bin/pint --test database/migrations/2026_08_28_110000_create_finance_invoices.php tests/Feature/FinanceModule/InvoiceSchemaTest.php`
  - PASS.
- `php artisan migrate:fresh --env=testing --force --quiet` with the repository's documented PHPUnit test key supplied only to that process
  - PASS.
- PostgreSQL migration `up()` and `down()` are compiled under the PostgreSQL grammar in the schema test and assert partial indexes, checks, signed `BIGINT` columns, cascades, deferred restrictive relationships, table drops, and revision-currency constraint removal.

## Concerns / boundaries

- A live PostgreSQL server was not available in this task. PostgreSQL-specific safety is verified through Laravel's PostgreSQL DDL compilation, while all behavioral constraint tests run against SQLite.
- Running `migrate:fresh` without a process-level application key stops in the pre-existing encryption migration before reaching this schema. The verified rerun used the deterministic test key already documented in `backend/phpunit.xml`; no environment file or secret was changed.
- Parallel untracked Quote/Project work was present in the shared worktree and was deliberately left untouched.
- No push, tag, deploy, version bump, or release action was performed.
