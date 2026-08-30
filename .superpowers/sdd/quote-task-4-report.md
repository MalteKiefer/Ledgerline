# Quote Task 4 Report

## Status

Task 4 from `docs/superpowers/plans/2026-08-28-finance-quotes-workflow.md` is complete in the `finance-module-rewrite` worktree.

The implementation adds the complete listed Quote DTO, port, query, record, repository, settings, and clock surface, registers the adapters in `FinanceServiceProvider`, and covers the persistence behavior in `QuotePersistenceTest`.

## Commit

`9f3f6e9b feat(finance): persist owner scoped quote aggregates`

The commit contains exactly the 25 Task 4 files. It does not contain Payment, Project, migration, Provider-external, legacy compatibility, push, tag, deployment, or unrelated changes.

## Behavior delivered

- `QuoteId` carries the explicit owner and public UUID; aggregate reads and revision history cannot cross owners.
- Quote creation persists the generic series, quote extension, and mutable draft in one transaction.
- Draft updates lock in the documented order (`document series -> quote extension -> current revision -> draft`) and use a compare-and-swap version update. A stale version returns the unchanged current `QuoteView`.
- Revision history is returned newest first and stays bound to the owner/series aggregate.
- Quote pages apply owner, query, persisted status, derived effective status, and published-date filters. Ordering is portable and deterministic: non-null `published_at DESC`, then internal series ID descending, with drafts last.
- Effective expiry uses the injected `Clock` and the owner's configured timezone; the quote remains valid through the last microsecond of `valid_until`.
- Partner and product references resolve only through authenticated owner-scoped Infrastructure adapters.
- Settings expose number format/floor, validity, payment terms, timezone, and sender display identity without SMTP host, username, or password.
- Idempotency attempts the unique insert first. A conflict returns `in_progress`, a completed success returns `replay` with its stored result, a failure returns its stable error, and hash reuse throws `idempotency_key_reused`.
- The insert runs in a nested transaction/savepoint so PostgreSQL can recover from a unique violation even when the caller already owns an outer transaction.
- Successful and failed operation outcomes are terminal and cannot be overwritten by a late competing completion.
- Server-controlled owner, numbering, current-revision, operation-result, delivery-state, and conversion-target fields are not mass assignable.
- All Application DTOs, ports, and queries remain free of Laravel, Eloquent, facade, HTTP, and legacy-model imports.

## TDD evidence

1. Initial RED: three tests failed because the Quote records and repository port did not exist.
2. Aggregate GREEN: owner UUID reads, draft creation, CAS, protected fields, and ordered revisions passed with 3 tests / 56 assertions.
3. Pagination RED: three new tests failed on the deliberate unimplemented page method and missing queries.
4. Pagination GREEN: owner filters, expiry/date/query/status filters, stable pagination, and readonly queries passed with 6 tests / 68 assertions.
5. Adapter/idempotency RED: four tests failed because reference, settings, clock, and operation ports were absent.
6. Adapter/idempotency GREEN: all behaviors passed with 10 tests / 94 assertions.
7. Terminal-operation RED: a late `fail()` changed an already successful reservation to failed.
8. Terminal-operation GREEN: completed operation results became immutable; 10 tests / 98 assertions passed.
9. Clock-boundary RED: replacing the injected clock with the next owner-local day did not expire the quote.
10. Clock-boundary GREEN: repository projection now uses the injected clock and owner timezone; 11 tests / 100 assertions passed.

## Verification

- `php artisan test tests/Feature/FinanceModule/Quotes/QuotePersistenceTest.php`
  - PASS: 11 tests, 100 assertions.
- Quote/Foundation focused suite (`FinanceModuleBootstrapTest`, `DocumentPersistenceTest`, `QuoteSchemaTest`, `QuotePersistenceTest`)
  - PASS: 60 tests, 338 assertions before the final two focused TDD additions; both additions were then rerun in the final Quote suite.
- `FILES_DISK=local php -d memory_limit=1G vendor/bin/phpunit tests/Feature/FinanceModule tests/Unit/Modules/Finance`
  - PASS: 324 passed, 2 skipped, 1,525 assertions.
- `vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G --no-progress`
  - PASS: zero errors.
- `vendor/bin/pint --test app/Modules/Finance/Application app/Modules/Finance/Infrastructure/Persistence app/Modules/Finance/Infrastructure/Settings app/Modules/Finance/Infrastructure/Time app/Modules/Finance/FinanceServiceProvider.php tests/Feature/FinanceModule/Quotes`
  - PASS.
- PHP syntax check over all 25 Task 4 files
  - PASS.
- `git diff --cached --check`
  - PASS before commit.

## Broader-suite notes

The first broad Artisan run exhausted PHP's default 128 MiB while decoding a large response in the parallel `LegacyProjectCompatibilityTest`. Repeating with 1 GiB exposed one environment-only S3 configuration failure in that same Project test. The final run used the repository's established `FILES_DISK=local` test override and passed completely.

## Remaining concerns

No known Task 4 correctness defect remains. PostgreSQL concurrency was not run against a live PostgreSQL server; the adapter nevertheless uses the database unique constraint as the arbiter and a nested transaction/savepoint specifically to keep the conflict path executable under PostgreSQL transaction semantics.

`Application/Ports/Clock.php` is a shared Foundation-style port and is also referenced by the parallel invoice/payment plan. Later integration should reuse this committed interface and its binding instead of creating a duplicate clock contract.

No push, tag, deployment, PR, or integration action was performed.

## Review round 1

### Status

All eight requested review findings are addressed in the follow-up commit containing this report. The parallel Project changes and `FinanceServiceProvider` were left untouched and are not part of the Quote commit.

### Corrections delivered

- Explicit owner predicates now remove only the ambient `owner` scope and retain `SoftDeletingScope`. Deleted quotes are unavailable through get, page, revisions, CAS updates, and quote-bound operation reservations; deleted partners and products are rejected by the reference resolver.
- `partnerId` is an explicit create/update port argument. Live owner membership is checked under a row lock in the same transaction, creation persists it, and a successful draft CAS changes or clears it in the same versioned update. `QuoteView` exposes the persisted value.
- Idempotency replay now requires the same nullable document-series identity as well as the same canonical request hash. Reusing an owner/operation/key for another quote or for a quote-less operation raises `idempotency_key_reused`; `QuoteId.ownerId` must match the explicit owner.
- Operation inputs are validated before persistence: positive owner, nonblank operation/key with portable byte limits, and exactly 64 lowercase hexadecimal SHA-256 characters. SQLite and PostgreSQL therefore receive the same accepted input domain.
- Quote listing now performs filter, count, stable ordering, limit, and offset in the database. Search covers UUID, number, mutable draft JSON, and the current published revision snapshot when the draft no longer exists. SQL wildcard characters are treated literally, and PostgreSQL UUID values are explicitly cast to text.
- Persisted/effective-status filters remain SQL-side, with portable SQLite/PostgreSQL JSON extraction. Published-date boundaries are calculated in the owner's timezone and passed to storage as UTC half-open intervals.
- Create and successful CAS paths write one injected-clock instant to both generic document root and Quote extension. Root, extension, partner change, version increment, and draft mutation remain in one transaction and preserve the documented lock order.
- The original idempotency test is explicitly named sequential. An opt-in PostgreSQL runtime test (`FINANCE_QUOTE_PG_CONFLICT_TEST=1` while the test database uses `pgsql`) executes the real unique-violation recovery path; local SQLite reports it as skipped rather than claiming PostgreSQL coverage.

### Review TDD evidence

1. Soft-delete RED: deleted quote history remained readable, and deleted partner/product references resolved. GREEN: two tests, seven assertions.
2. Partner RED: the port rejected the explicit named partner argument. GREEN: owned create, foreign/deleted rejection, CAS change/stale/clear behavior; four partner-related tests, 22 assertions.
3. Idempotency RED: the same key/hash replayed for a different quote, owner mismatch was accepted, and invalid values reached database constraints. GREEN: five focused tests, 27 assertions.
4. Listing RED: published snapshot search returned no row, pagination had no database count/limit/offset, and New York local-day filtering returned no row. GREEN: six focused list/date tests, 21 assertions.
5. Timestamp RED: CAS did not advance the document root and create ignored the injected clock. GREEN: create and update use identical root/extension instants; one test, six assertions.
6. Portability self-review RED: PostgreSQL UUID search lacked a text cast, and wildcard search treated `%` as SQL syntax. GREEN: both are covered by executable query assertions/behavior tests.

### Review verification

- `php artisan test tests/Feature/FinanceModule/Quotes/QuotePersistenceTest.php`
  - PASS: 23 passed, one opt-in PostgreSQL test skipped, 149 assertions.
- Quote/Foundation focused suite (`FinanceModuleBootstrapTest`, `DocumentPersistenceTest`, `QuoteSchemaTest`, `QuotePersistenceTest`)
  - PASS: 74 passed, one skipped, 394 assertions.
- `FILES_DISK=local php -d memory_limit=1G vendor/bin/phpunit tests/Feature/FinanceModule tests/Unit/Modules/Finance`
  - PASS: 391 passed, three skipped, 1,799 assertions.
- `vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G --no-progress`
  - PASS: zero errors.
- Pint over the four changed Quote production files and `QuotePersistenceTest`
  - PASS.

### Remaining concerns

The opt-in PostgreSQL unique-conflict test was not run locally because this worktree is configured for in-memory SQLite and no PostgreSQL test service/credentials were supplied. The executable path is present and gated by both the PostgreSQL driver and `FINANCE_QUOTE_PG_CONFLICT_TEST=1`; it does not substitute a simulated SQLite assertion for PostgreSQL evidence.

No push, tag, deployment, PR, or integration action was performed.
