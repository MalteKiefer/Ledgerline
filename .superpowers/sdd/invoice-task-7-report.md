# Invoice Plan Task 7 – Implementation Report

## Status

Task 7 is implemented with an atomic invoice-finalization boundary. Finalization now revalidates and recalculates the stored draft before number allocation, freezes the authoritative invoice snapshot, publishes the revision and PDF, records exact inventory effects, advances workflow/version state, appends audit activity, and completes idempotency in one database transaction. A retry returns the stored finalization result without rendering, numbering, activity, or stock duplication.

## Delivered behavior

- `FinalizeInvoice` coordinates rendering, owned storage writes, atomic repository work, and safe storage compensation after any failure.
- `EloquentInvoiceRepository::finalizeAtomically` locks series, invoice, and current revision in a stable order; verifies draft/source state; recalculates exact totals; freezes number, company, and source data; publishes the current revision; records inventory; applies CAS workflow updates; and appends `revision.published` plus `invoice.finalized` activity.
- The Task 5 `InvoiceRepository::finalize` contract remains intact. The Task 7 orchestration uses the additive `finalizeAtomically` contract.
- `LockedInvoiceNumberAllocator` provides owner/year-scoped allocation from the configured floor and format. The unique sequence row plus row lock serializes concurrent first allocation; the counter and any allocation roll back with the outer transaction.
- `LegacyStockLedgerAdapter` validates owner-scoped live hardware products, locks products in numeric order, aggregates duplicate product lines before the port call, writes signed exact scale-4 movements, and treats the invoice/product reference as idempotent.
- A partial unique index prevents duplicate invoice-sale movement references for the same owner and product.
- Published invoice revisions remain immutable through the existing persistence guards; source-draft identity remains unchanged across finalization and subsequent source replay.

`FinalizedInvoice` already exposed the complete required result (`InvoiceView`, revision, PDF path/hash, and timestamp), so no DTO shape change was necessary.

## TDD evidence

The initial finalization test failed because `FinalizeInvoice` did not exist, then passed after the smallest happy-path implementation. The existing Task 5 repository suite then exposed an incompatible method-signature replacement; the old contract was restored and Task 7 moved to an additive method. The migration regression initially failed because a fresh install ran the requested 2026 migration before the legacy 2027 product tables existed; the two-stage compatibility/fresh-install design made it pass. A focused model regression then failed on legacy scale-3 casts and passed after the authorized scale-4 cast correction. Rollback, source immutability, allocator, exact inventory, and PostgreSQL runtime coverage were added around the same boundary.

## Approved scope corrections

The written Task 7 migration timestamp predates the migration that creates `finance_products` and `finance_stock_movements`. Per the explicit implementation decision, this is corrected additively without rewriting deployed history:

- `2026_08_28_110050_harden_invoice_stock_idempotency.php` is an idempotent deployed-upgrade compatibility migration and acts only when the target tables already exist.
- `2027_02_28_100100_guarantee_invoice_stock_idempotency.php` runs after the legacy product tables and guarantees scale/index invariants on a fresh install.

Database scale four would otherwise be lost when values pass through legacy Eloquent models. The explicitly authorized minimal expansion changes only `FinanceProduct` stock casts and `FinanceStockMovement::qty` to `decimal:4`, with focused legacy expectations updated accordingly.

## Verification

- Relevant Finance suite after review round 1: 220 tests discovered, 216 passed, 1,121 assertions, 4 skipped.
- The skipped cases are opt-in PostgreSQL/runtime contracts when their external environment is unavailable. Task 7's test uses `FINANCE_TEST_PGSQL_URL`, creates an isolated schema, executes the PostgreSQL migrations, validates scale/index DDL, and runs two allocator processes against the same first sequence.
- PHPStan on all changed Task 7 production paths and the two approved models: 0 errors (run with a 1 GB PHP memory limit after the default 128 MB process limit was exhausted).
- Pint completed successfully on the explicit Task 7 allowlist.
- `git diff --check` completed without whitespace errors.

## Provider integration required later

`FinanceServiceProvider` was deliberately not modified because Quote Task 7/8 owns concurrent edits there. The integration owner must add these bindings:

```php
InvoiceNumberAllocator::class => LockedInvoiceNumberAllocator::class
InventoryMovementPort::class => LegacyStockLedgerAdapter::class
```

The existing `InvoiceRepository`, `DocumentRenderer`, `DocumentStorage`, and logger bindings satisfy the other constructor dependencies.

## Remaining concerns

- The real PostgreSQL execution path is present but was skipped locally because `FINANCE_TEST_PGSQL_URL`/`pdo_pgsql` was not available; CI with PostgreSQL must exercise it.
- SQLite stores stock quantities as canonical text because its numeric affinity loses precision near the full `NUMERIC(16,4)` range; PostgreSQL enforces `NUMERIC(16,4)` physically. Both paths use checked scale-4 integer arithmetic in application services.
- No provider change, push, tag, deployment, or historical migration rewrite is included.

## Review round 1

The inventory classification boundary no longer trusts line snapshot metadata. Finalization gathers every referenced product ID, locks live owner-scoped product rows in numeric order, derives the authoritative product kind, writes that kind into the final snapshot, rejects an explicit mismatch with `invoice_inventory_kind_mismatch`, always aggregates hardware, and never creates service movements. Regression coverage includes hardware mislabeled as service, hardware with an omitted kind, and service mislabeled as hardware; all rejection paths remain before number allocation.

Legacy stock arithmetic no longer uses float casts or database `SUM`:

- `StockLedger::move` accepts the existing integer/float calls plus exact decimal strings, converts once to scale-4 integers, checks the `NUMERIC(16,4)` range before writing, and formats canonical strings.
- `StockLedger::recompute` streams ledger values as text, performs checked integer addition, writes and returns a canonical scale-4 string, and leaves stock unchanged on overflow.
- `FinanceProduct::isLowOnStock` compares scaled integers, including adjacent values near `999999999999.9999` that collapse when converted to float.
- The invoice inventory adapter applies the same maximum and checks stock overflow before inserting a movement.

SQLite migration storage was changed from numeric affinity to text so the full scale-4 range remains exact. Down validates every value before dropping an index or changing a column. SQLite table rebuilds explicitly preserve the existing partial live-SKU index and the partial invoice-sale idempotency index; repeated Up and lossy Down behaviors have dedicated regressions.

Review TDD evidence included red reproductions for the kind bypasses, large positive and negative arithmetic, recompute precision, move/recompute overflow, large reorder comparison, invoice-adapter overflow, lossy Down index mutation, and SQLite partial-index loss during an idempotent Up. Each passed after its focused implementation change.

## Review round 2

The legacy stock endpoint now validates quantities at a maximum of four decimal places and passes the validated request string unchanged into `StockLedger`. The regression proves that `0.0001` is stored exactly, while `1.23456`, locale-comma input, and scientific notation all return 422 and append no movement.

Both stock-hardening Down preflights now require values to satisfy both conditions of the former `NUMERIC(12,3)` columns: the fourth decimal must be zero and the absolute value must not exceed `999999999.999`. Positive and negative `999999999999.9990` reproductions prove rejection before the partial index, schema declarations, or stored values change. The exact positive/negative boundary passes Down/Up through both the fresh-install and compatibility migrations.

The boundary test also exposed SQLite parent-table rebuild cascades that erased stock history. SQLite now changes only the validated column affinity declarations in place, without rebuilding the product parent. This preserves append-only movements and every foreign-key action; the regression verifies exact product/movement values, the partial invoice-sale index, `PRAGMA integrity_check`, and `PRAGMA foreign_key_check` after both round trips.

Review-round-2 TDD evidence:

- The stock endpoint test first returned 500 for `1.23456`; it passes after request-scale validation and removal of the controller float cast.
- The overflow migration test first allowed the out-of-range value; it passes after exact string-based integer/range checks in both preflights.
- The boundary round-trip initially lost the ledger movement through SQLite cascade behavior; the non-rebuilding affinity update preserves it.
- Final focused verification: 34 product/finalization tests, 33 passed, 251 assertions, 1 PostgreSQL skip before the final integrity assertions were added; the final broad Finance verification is recorded below.

Final review-round-2 verification:

- Relevant Finance suite: 223 tests discovered, 219 passed, 1,181 assertions, 4 PostgreSQL/runtime skips.
- Fresh SQLite migration completed successfully with the test `APP_KEY` and in-memory database.
- PHPStan on the changed controller and both migrations: 0 errors with a 1 GB memory limit.
- Pint on the five explicit review-round-2 files: passed.
- PostgreSQL remains opt-in through `FINANCE_TEST_PGSQL_URL`; it was unavailable locally, so CI must execute the native `NUMERIC(12,3)` Down/Up path.
