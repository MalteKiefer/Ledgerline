# Quote Task 5 Report

## Status

Task 5 from `docs/superpowers/plans/2026-08-28-finance-quotes-workflow.md` is complete on the current `finance-module-rewrite` worktree.

The implementation adds framework-free Quote input DTOs, an exact-decimal draft factory, authoritative totals preview, create/update/discard commands, create idempotency, version compare-and-swap delegation, and atomic Quote draft activities. No provider, Project, Recurring, HTTP, legacy, or frontend file was changed for this task.

## Plan scope correction

The written Task 5 file list did not include the existing `QuoteRepository` port or `EloquentQuoteRepository`, but the same task requires persistent later-draft discard and atomic `quote.created`, `quote.draft.updated`, and `quote.draft.discarded` activities. Those behaviors cannot be implemented in the listed Application files without importing Laravel/Eloquent into Application.

After explicit approval, the scope was minimally widened to those two existing Quote repository files. The port owns draft discard and the create unit-of-work contract, while the adapter owns idempotency reservation/completion, deletion, CAS, lock ordering, timestamps, and append-only activity persistence in its transactions. The existing provider binding already resolves the extended adapter, so `FinanceServiceProvider` remained untouched.

## Behavior delivered

- `QuoteLineData` accepts the wire fields `description`, `quantity`, `unit`, `unit_price`, `tax_rate`, `kind`, and `product_id`; decimal-bearing fields are typed as strings, so JSON numbers do not cross the input boundary.
- `QuoteDraftFactory` converts quantity through `DecimalQuantity`, money through `Money`, and percentage tax/discount strings exactly to basis points. It uses no floats.
- Drafts require 1–200 lines. Dates are strict `YYYY-MM-DD`; omitted issue dates use the owner's local date, and omitted validity uses `quote_valid_days` from that issue date.
- Partner and unique product references are owner-validated before calculation/persistence, including soft-delete rejection through the existing resolver.
- All line amounts share the Quote-level normalized currency by wire-contract design. Percentage and fixed discounts flow through the shared `Discount`/`DocumentCalculator` contract.
- Client net/VAT/gross values are optional control checks only. A mismatch raises `control_totals_mismatch`; controls never enter the stored payload or replace calculated totals.
- Stored payloads contain canonical decimal strings plus authoritative scaled quantities, minor-unit prices, basis-point rates, normalized discount data, integer totals, and integer tax breakdowns.
- `PreviewQuoteTotals` returns the same authoritative totals/default dates without persistence.
- `CreateQuote` hashes the canonical raw request (before time/settings defaults) and delegates one repository unit of work. Reservation, aggregate/draft/activity creation, binding the operation to the series, and successful completion share one physical transaction. A completion exception rolls everything back; retrying the same key creates exactly once and later replays the original UUID. The same key with changed input raises `idempotency_key_reused`; retries across owner midnight remain stable and do not revalidate references deleted after the successful original write.
- `UpdateQuoteDraft` validates input and delegates the expected version to repository CAS. Only the winner changes payload/partner/version and emits `quote.draft.updated`; stale callers receive the current unchanged view.
- `DiscardQuoteDraft` rejects the only initial draft with `initial_draft_cannot_be_discarded`. A later draft based on an immutable revision is removed under the established series → extension → current revision → draft lock order, increments the aggregate version/timestamps, and emits `quote.draft.discarded`. A stale retry returns the current no-draft view without another event.
- Root/extension updates and each activity stay within the same repository transaction. Application DTOs, services, queries, and commands contain no Laravel, Eloquent, HTTP, legacy-model, or Infrastructure imports.
- Quote settings reads no longer call `UserSetting::for()`. Missing settings return the established defaults without inserting a `user_settings` row, keeping preview strictly read-only.

## TDD evidence

1. Initial RED: all seven application tests errored because the DTOs, factory, query, and commands did not exist.
2. Parser/preview GREEN: exact decimal parsing, owner-local defaults, authoritative controls, input limits, and owner reference rejection passed with four tests and 23 assertions.
3. Command RED/GREEN: missing Create/Update/Discard commands and persistence behavior became nine passing application tests with create replay, CAS winner/stale behavior, activity counts, initial-draft rejection, and later-draft discard.
4. Idempotency-midnight RED: the same raw request/key raised `idempotency_key_reused` after the owner-local date rolled over because the hash included resolved defaults. GREEN: hashing the canonical raw request made the replay stable.
5. Replay-reference RED: replay failed after the originally valid partner/product was soft-deleted. GREEN: reservation/replay now precedes reference recalculation; new writes still validate every live reference.
6. PHPStan exposed an imprecise customer-array return type. The implementation now preserves and documents string keys without suppression or baseline changes.
7. Review RED/GREEN (settings): previewing for an owner without settings inserted a `user_settings` row. The adapter now performs nullable reads and the regression test proves the row count remains zero.
8. Review RED/GREEN (atomic idempotency): an injected failure during operation completion was not observed because completion lived outside aggregate persistence. The repository unit of work now updates the operation through the same outer transaction; the test proves zero durable operation/series/draft/activity rows after failure, then exactly one of each after same-key retry and replay.

## Verification

- `php artisan test tests/Feature/FinanceModule/Quotes/QuoteDraftApplicationTest.php tests/Unit/Modules/Finance/Domain/Shared/DocumentCalculatorTest.php`
  - PASS: 29 tests, 187 assertions.
- `php artisan test tests/Feature/FinanceModule/FinanceModuleBootstrapTest.php tests/Feature/FinanceModule/Quotes/QuoteDraftApplicationTest.php tests/Feature/FinanceModule/Quotes/QuotePersistenceTest.php tests/Unit/Modules/Finance/Domain/Shared/DocumentCalculatorTest.php`
  - PASS: 59 passed, one opt-in PostgreSQL test skipped, 348 assertions.
- `FILES_DISK=local php -d memory_limit=1G vendor/bin/phpunit tests/Feature/FinanceModule tests/Unit/Modules/Finance`
  - PASS: 421 passed, four skipped, 2,009 assertions.
- `vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G --no-progress`
  - PASS: zero errors.
- Pint over every Task 5 production/test file plus the two approved repository files
  - PASS after formatting; final `--test` recorded before commit.

## Review round 1 verification

- `php artisan test tests/Feature/FinanceModule/Quotes/QuoteDraftApplicationTest.php`
  - PASS: 11 tests, 70 assertions.
- `php artisan test tests/Feature/FinanceModule/Quotes`
  - PASS: 47 passed, one opt-in PostgreSQL test skipped, 332 assertions.
- `php artisan test tests/Feature/FinanceModule/Quotes/QuoteDraftApplicationTest.php tests/Unit/Modules/Finance/Domain/Shared/DocumentCalculatorTest.php`
  - PASS: 31 tests, 207 assertions.
- `FILES_DISK=local php -d memory_limit=1G vendor/bin/phpunit tests/Feature/FinanceModule tests/Unit/Modules/Finance`
  - PASS: 428 passed, six skipped, 2,077 assertions.
- PHPStan over `app/Modules/Finance`
  - PASS: zero errors.
- Focused Pint `--test` over the five changed production/test files
  - PASS.

## Remaining concerns

The PostgreSQL opt-in operation-conflict path belongs to Task 4 and remains locally skipped because no PostgreSQL test service/credentials were supplied. Task 5's calculator, input, command, CAS, and activity coverage ran on the configured in-memory SQLite database.

No push, tag, deployment, PR, provider change, or integration action was performed.
