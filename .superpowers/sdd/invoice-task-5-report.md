# Invoice plan Task 5 report

Date: 2026-08-28
Branch: `codex/finance-module-rewrite`

## Status

Implemented Task 5's owner-scoped invoice, payment, allocation, idempotency, and recurring persistence surface using strict test-first iterations. `FinanceServiceProvider` was intentionally not changed because Task 6 is being developed in parallel; feature tests construct the repositories and clock explicitly.

## Delivered

- Readonly, validated invoice/payment/recurring IDs and data/result DTOs, including positive IDs, uppercase ISO currency, integer minor units, canonical scale-4 quantities, bounded opaque idempotency keys, bounded payment metadata, and unique invoice targets per allocation batch.
- Owner-scoped Eloquent records for invoices, deliveries, sequences, idempotency records, payments, allocation batches/entries, recurring templates/versions/runs.
- Model- and builder-level mutation guards for append-only or repository-owned records, covering normal, quiet, bulk, increment/decrement, touch, upsert, update-or-insert, force-delete, truncate, and SQLite `INSERT OR REPLACE` paths. Database triggers remain the final guard for raw writes and owner cascades.
- Transactional repositories with explicit owner predicates even when global scopes are disabled, deterministic lock order (`series -> invoice -> revision -> payment -> allocations -> delivery/run`), optimistic invoice compare-and-set, delivery compare-and-set, exact signed balance recomputation, allocation reversal, stable pagination, and owner-safe lookups.
- Hash-only idempotency persistence with request-mismatch detection, replay, pending/failed states, owner isolation, and stable payment-source conflict normalization. Raw client keys are never exposed by DTOs or persisted.
- Optional real PostgreSQL execution path gated by `FINANCE_TEST_PGSQL_URL`; it creates an isolated schema, applies Tasks 1-4 migrations, and exercises owner isolation, idempotency replay, PostgreSQL `FOR UPDATE` lock order, allocation/reversal, and recurring run locks. It safely skips when PostgreSQL is unavailable.

## TDD evidence

The feature was developed in red/green slices:

1. Missing Task 5 surface and value objects.
2. Owner model and repository isolation, including a real Sanctum request.
3. Direct/quiet/bulk/replace mutation escapes.
4. Idempotency and stable recurring pagination/locks.
5. Invoice draft CAS, finalization replay, and delivery CAS.
6. Payment recording, allocation, reversal, signed projections, cross-owner and sign failures.
7. Bounded payment metadata, duplicate allocation targets, source uniqueness normalization, and expanded mutation entry points.

## Verification

- `php artisan test tests/Feature/FinanceModule/FinanceInvoicePersistenceTest.php`
  - PASS: 61 tests discovered, 60 passed, 316 assertions, 1 optional PostgreSQL skip.
- `php artisan test tests/Feature/FinanceModule/DocumentCoreSchemaTest.php tests/Feature/FinanceModule/InvoiceSchemaTest.php tests/Feature/FinanceModule/PaymentSchemaTest.php tests/Feature/FinanceModule/RecurringInvoiceSchemaTest.php`
  - PASS: 81 tests discovered, 78 passed, 493 assertions, 3 optional PostgreSQL skips.
- `vendor/bin/pint --test ...Task 5 paths...`
  - PASS.
- PHPStan over the exact staged Task 5 application-file allowlist with `--memory-limit=1G --no-progress`
  - PASS: 0 errors.
- Fresh full migration against isolated SQLite `:memory:` with an explicit test application key
  - PASS, including all Finance and later migrations.
- Full backend suite (diagnostic, excluding the already failing `AccountLifecycleTest` after the default 128 MB run exhausted memory)
  - NOT GREEN: 2,038 tests, 1,991 passed, 16 failures, 5 errors, 26 skipped, 1 risky.
  - Visible unrelated causes include Windows line-ending/`rm` assumptions and missing S3 bucket configuration in storage/account tests. The targeted Task 5 and Finance schema gates above remain green.

## Concerns and follow-up

- No `FinanceServiceProvider` bindings were added or staged, per instruction. Runtime constructor resolution for the new ports must be registered by the coordinating integration task; tests use explicit construction and did not require provider mutation.
- A broad final `app/Modules/Finance` Pint/PHPStan rerun began including new parallel Project-task files that arrived after Task 5's earlier clean module run; it currently reports formatting work and 10 PHPStan errors only in those unstaged Project files. The exact staged Task 5 allowlist passes both gates and none of the parallel files were changed or staged here.
- The local persistent `backend/database/database.sqlite` reports `database disk image is malformed`. It was not modified or repaired. Migration verification used a fresh isolated in-memory SQLite database instead.
- The PostgreSQL runtime contract is present but skipped locally because `FINANCE_TEST_PGSQL_URL`/`pdo_pgsql` were unavailable. CI with that variable should execute it.
- No push, tag, deploy, merge, branch cleanup, or unrelated file mutation was performed.
