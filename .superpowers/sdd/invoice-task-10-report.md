# Invoice Plan Task 10 Report

Date: 2026-08-29
Branch: `codex/finance-module-rewrite`

## Status

Implemented the Task 10 payment application boundary for exact signed payments, partial and multi-invoice allocations, unapplied overpayments, append-only reversals, effective invoice balance status, and deterministic non-mutating suggestions. The Application layer remains framework-free. The approved minimal scope correction added the Payment repository read projection, two suggestion DTOs, and atomic persistence/status extensions that the plan's file list omitted.

## Delivered behavior

- `RecordPayment`, `AllocatePayment`, and `ReversePaymentAllocation` are the only new use-case entry points. Manual and imported payments share `RecordPaymentData`; source metadata is their only distinction.
- Payment and allocation DTOs accept exact signed integers only, reject zero/float input at the strict PHP boundary, enforce uppercase three-letter currency, and reject values outside the database's `±99,999,999,999,999` minor-unit range.
- Payment-method and known bank-transaction references are owner-scoped and soft-delete aware. A new request with a foreign reference returns owner-safe not-found and leaves no payment or idempotency row. An exact completed replay resolves before those mutable references are revalidated.
- Source-key idempotency is independent of the caller transport key. For an existing owner/source tuple, an exact canonical payload completes the new transport operation against the original payment; a changed amount, currency, UTC-normalized second-precision receipt time, reference, counterparty, or payment method fails with stable `payment_source_conflict` and rolls back the new operation.
- The source comparison is derived from the immutable payment row and covers amount, currency, canonical receipt time, reference, counterparty, payment method, source type, and source key. The database's owner/source unique index remains the final race arbiter; a losing concurrent insert retries the comparison after rollback instead of leaking a driver exception.
- Allocation request hashes include payment, invoice-ID-sorted canonical lines, exact signed amounts, and optional expected payment version. Reordering the same unique invoice lines is an exact replay, while a changed payload produces the stable idempotency reuse error. Exact replay returns the original batch before current-version CAS.
- The existing deterministic lock order remains `series -> invoice -> revision -> payment -> allocation entries`; target invoices are locked in ascending numeric order. Optional payment-version CAS rejects stale writers before any batch, entry, activity, or projection change.
- Partial and complete allocations project `partially_paid` and `paid`; reversal returns an otherwise sent invoice to `sent`. Cancelled wins over settlement. Draft, cancelled, foreign-owner, cross-currency, cross-sign, and over-magnitude targets fail atomically.
- One payment can allocate across multiple invoices. A payment larger than invoice open amount retains an exact unapplied remainder. Negative refunds settle negative credit notes using negative entries.
- Every successful allocation appends one `payment.allocated` document activity in the same transaction. Reversal appends the exact negation linked through `reverses_allocation_id`, writes one `payment.allocation_reversed` activity, and cannot be performed twice. Idempotent replay appends neither entry nor activity.
- Suggestions read through `PaymentRepository::suggestionContext()`. Exact normalized invoice-number/reference evidence has strict priority, followed by exact currency/remaining amount and then customer/date evidence. Cross-currency, opposite-sign, draft, paid, and cancelled candidates are excluded.
- A unique best score returns `suggested`; equal best scores remain `ambiguous` in deterministic score/number/ID order. Every result requires confirmation, and the query has no mutation method or auto-apply path.

## TDD evidence

Each production slice followed observed RED then focused GREEN:

1. Missing record command and source fields on `PaymentView`.
2. Foreign payment-method and bank-source acceptance.
3. Missing allocation command, workflow-only instead of effective status, and absent activities.
4. Cancelled target acceptance and missing expected-version CAS.
5. Missing reversal command/activity/version contract.
6. Missing suggestion query/DTO/port implementation and missing Eloquent read projection.
7. Mutable source validation incorrectly preceding exact record replay.
8. Missing exact minor-unit database-range validation.
9. Partial-reference plus amount/customer/date evidence incorrectly outranking an exact normalized reference; lexicographic score bands fixed the regression.
10. Reordering the same multi-invoice allocation initially produced `idempotency_key_reused`; invoice-ID-sorted request hashing made the semantic replay canonical without changing entry/result order.
11. A second transport key for the same exact source initially produced `payment_source_conflict`; immutable-row canonical comparison now replays the original payment and completes both operation records.
12. Removing UTC normalization made equivalent `10:15Z` and `12:15+02:00` source retries fail; the mutation test proved the canonical timestamp guard.
13. Production container resolution initially failed because `PaymentRepository` was not instantiable; the additive provider binding resolves `EloquentPaymentRepository` with its existing dependencies.

## Verification

- Task 10 plus production-container suite: 28 tests passed, 128 assertions.
- Payment/application/provider/persistence/schema/domain regression: 134 tests discovered, 128 passed, 632 assertions, 6 optional PostgreSQL skips.
- PostgreSQL now has real two-process paths for same-key allocation replay, same-key payment recording, concurrent same-source recording across distinct transport keys, and two different payments racing for one invoice remainder. The latter asserts one success, one stable `invoice_overallocated`, exact final allocation, and process completion within lock/statement timeouts. All remain gated by `FINANCE_TEST_PGSQL_URL` and safely skipped locally because PostgreSQL is not configured.
- Task-scoped Pint passed.
- Task production PHPStan paths passed at level 10 with a 512 MB analysis limit and unmatched global ignores disabled for the narrow run.
- PHPStan over the three touched feature-test files still reports 15 pre-existing findings (14 legacy typing findings in `FinanceInvoicePersistenceTest` and the intentional float-boundary call in `PaymentApplicationTest`). The new PostgreSQL worker result initially added one mixed-to-string finding; runtime payload validation removed it, leaving the baseline count unchanged.
- A broader 804-test Finance run (512 MB) reached 784 passes and 15 optional skips, but remains non-green with four failures and one error in unrelated Project/Quote/Invoice-finalization paths. The full backend run also exposes the existing test-environment S3 configuration failure (`AwsS3V3Adapter` receives a null bucket) in `AccountLifecycleTest`; the default 128 MB limit additionally exhausts in long PDF/full-suite runs. No Task 10 test failed in either broader run.

## Commits and scope

- `c3f666cd feat(finance): apply exact invoice payments` is the verified write-side checkpoint. It includes only Task 10 payment files/tests plus the effective Invoice view projection. A parallel `deleteDraft` hunk in the same repository file was excluded with partial-index staging and later committed by its owner.
- `3b5d03f5 feat(finance): suggest exact payment allocations` contains the suggestion/read-side, canonical allocation replay hardening, and initial report.
- Review follow-up adds the production `PaymentRepository => EloquentPaymentRepository` binding, source-key replay semantics, and PostgreSQL concurrency contracts in a separate explicit-path commit.
- No HTTP route/controller was added because Task 10 lists no HTTP surface; API activation remains a later plan task.
- No push, tag, deploy, migration edit, or unrelated Project/Quote change was performed.
