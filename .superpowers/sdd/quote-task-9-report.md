# Quote Task 9 Report

## Status

Implemented Task 9 with test-first coverage for decisions, derived expiry, duplication, and the quote-to-invoice boundary. `FinanceServiceProvider` was intentionally not changed, as directed.

## Behavior delivered

- Accept and decline operate on the exact current published revision, enforce CAS version, pending-draft, replaced/stale, transition, and owner-local end-of-day validity rules.
- Decision status, timestamp, aggregate version, activity, and idempotency completion are written in one database transaction. Same-key retries replay the stored aggregate.
- Expiry remains derived; persisted status is unchanged. An accepted quote that passes `valid_until` now also reports `effective_status=expired` and is excluded from the non-expired accepted filter.
- Duplicate copies either the initial draft or the selected current immutable revision into an independent UUID with no number or revision state. Dates are reset through owner settings and totals/references are rebuilt by `QuoteDraftFactory`; client/stored totals are not trusted.
- Conversion accepts only an accepted, current, non-expired revision without a pending draft. The immutable snapshot and revision identity cross the framework-free `QuoteToInvoicePort` boundary.
- Series/revision locks plus the unique conversion row make different-key conversion races converge on one target. Operation completion, conversion row, status/timestamp, and activity are atomic. Target failure rolls back and the same key resumes.
- `LegacyInvoiceDraftAdapter` is the only new class importing the legacy `Invoice`; it creates an owner-scoped unnumbered draft with exact decimal strings, owner-validated partner reference, immutable customer/line data, and payment terms from settings.

## TDD evidence

- Initial RED: the new feature suite failed because `QuoteToInvoicePort` did not exist.
- Incremental GREEN: decision, duplicate, conversion, rollback/retry, foreign-owner/foreign-target, idempotency-reuse, derived-expiry, and adapter tests were added and passed.
- An opt-in PostgreSQL test (`FINANCE_TEST_PGSQL_URL`) runs two different conversion keys concurrently and verifies the second worker waits on the aggregate lock and replays the one committed invoice target. It is skipped when PostgreSQL is not configured.

## Verification

- `php artisan test tests/Feature/FinanceModule/Quotes/QuoteDecisionConversionTest.php tests/Feature/FinanceQuoteTest.php tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php`
- `vendor/bin/phpstan analyse` on all changed Task 9 production files with `--memory-limit=1G`
- `vendor/bin/pint` on all changed Task 9 PHP files
- `git diff --check`

Final scoped verification:

- Focused Task 9, legacy baseline, legacy quote, and Quote workflow: 79 tests, 78 passed, 328 assertions, 1 environment-gated PostgreSQL concurrency test skipped.
- PHPStan: 0 errors across every changed Task 9 production file.
- Pint `--test`: passed across every changed Task 9 PHP file.
- `git diff --check`: passed.

Commit SHA is recorded in the handoff after commit creation.

The broader `tests/Feature/FinanceModule/Quotes tests/Unit/Modules/Finance` run reached 278 passing tests and 1,163 assertions, with three PostgreSQL tests skipped, but it is not fully green: two pre-existing `QuoteSchemaTest` inserts omit the required Task-8 `finance_quote_deliveries.uuid` column and fail before Task-9 behavior is involved. The Task-9 scope does not permit changing that Task-8 schema test. A separate Dompdf process initially exhausted the default 128 MB limit; invoking PHPUnit directly with a 1 GB limit resolves that resource-only failure.

## Binding intentionally deferred

Production resolution of `ConvertQuoteToInvoice` still needs exactly this provider binding once provider coordination permits it:

```php
$this->app->bind(QuoteToInvoicePort::class, LegacyInvoiceDraftAdapter::class);
```

No `FinanceServiceProvider` changes are part of this commit.

## Concerns

- The PostgreSQL concurrency path is executable but environment-gated; the local SQLite run validates transactional rollback, replay, owner constraints, and sequential different-key convergence.
- The legacy adapter is deliberately temporary. The later invoice module should replace the one port binding without changing Quote application commands.
