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
- Delivery completion now locks and validates the invoice before mutating the delivery, permits only `finalized`/`sent` invoices, and requires the invoice compare-and-set to affect exactly one row. Any invoice conflict rolls back the delivery transition.
- Hash-only idempotency persistence with request-mismatch detection, replay, pending/failed states, owner isolation, and stable payment-source/allocation-batch conflict normalization. Raw client keys are never exposed by DTOs or persisted. Allocation batch hashes include the operation, so one user key can independently identify allocation and reversal without colliding.
- Optional real PostgreSQL execution paths gated by `FINANCE_TEST_PGSQL_URL`; they create an isolated schema, apply Tasks 1-4 migrations, and exercise owner isolation, operation-scoped idempotency replay, stable unique-conflict normalization, PostgreSQL `FOR UPDATE` lock order, allocation/reversal, recurring run locks, and a two-process concurrent allocation replay. They safely skip when PostgreSQL is unavailable.

## TDD evidence

The feature was developed in red/green slices:

1. Missing Task 5 surface and value objects.
2. Owner model and repository isolation, including a real Sanctum request.
3. Direct/quiet/bulk/replace mutation escapes.
4. Idempotency and stable recurring pagination/locks.
5. Invoice draft CAS, finalization replay, and delivery CAS.
6. Payment recording, allocation, reversal, signed projections, cross-owner and sign failures.
7. Bounded payment metadata, duplicate allocation targets, source uniqueness normalization, and expanded mutation entry points.
8. Review round 1: draft-invoice delivery rollback, exact invoice CAS, same-key allocate/reverse replay, orphaned-batch conflict normalization, and the PostgreSQL two-process concurrency path.

## Verification

- `php artisan test tests/Feature/FinanceModule/FinanceInvoicePersistenceTest.php`
  - PASS: 64 tests discovered, 62 passed, 326 assertions, 2 optional PostgreSQL skips.
- `php artisan test tests/Feature/FinanceModule/FinanceInvoicePersistenceTest.php tests/Feature/FinanceModule/InvoiceSchemaTest.php tests/Feature/FinanceModule/PaymentSchemaTest.php`
  - PASS: 112 tests discovered, 108 passed, 593 assertions, 4 optional PostgreSQL skips.
- `vendor/bin/pint --test app/Modules/Finance/Infrastructure/Persistence/EloquentInvoiceRepository.php app/Modules/Finance/Infrastructure/Persistence/EloquentPaymentRepository.php tests/Feature/FinanceModule/FinanceInvoicePersistenceTest.php`
  - PASS.
- PHPStan over the exact Task 5 application-file allowlist with `--memory-limit=1G --no-progress`
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
- The PostgreSQL runtime contracts, including the real two-process concurrency regression, are present but skipped locally because `FINANCE_TEST_PGSQL_URL` is unavailable. CI with that variable should execute them.
- No push, tag, deploy, merge, branch cleanup, or unrelated file mutation was performed.
