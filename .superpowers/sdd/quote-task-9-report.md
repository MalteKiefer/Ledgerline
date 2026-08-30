# Quote Task 9 Report

## Status

Implemented Task 9 and review rounds 1–2 with test-first coverage for decisions, derived expiry, duplication provenance, and the quote-to-invoice boundary. `FinanceServiceProvider` was intentionally not changed, as directed.

## Behavior delivered

- Accept and decline operate on the exact current published revision, enforce CAS version, pending-draft, replaced/stale, transition, and owner-local end-of-day validity rules.
- Decision status, timestamp, aggregate version, activity, and idempotency completion are written in one database transaction. Same-key retries replay the stored aggregate.
- Expiry remains derived; persisted status is unchanged. An accepted quote that passes `valid_until` now also reports `effective_status=expired` and is excluded from the non-expired accepted filter.
- Duplicate copies either the initial draft or the selected current immutable revision into an independent UUID with no number or revision state. Dates are reset through owner settings and totals/references are rebuilt by `QuoteDraftFactory`; client/stored totals are not trusted. Every target series now persists `source_type=quote_duplicate_operation` and its owner-validated duplicate operation ID as `source_id`. Following that durable operation identifies the exact owner-scoped source aggregate while allowing a new idempotency key to create another intentional copy without violating the foundation source uniqueness constraint.
- The duplicate partner now comes from the selected canonical draft/revision payload, not the mutable quote-series extension. It is revalidated through `QuoteDraftFactory`. A regression changes the partner in a later version draft, discards that draft, and proves duplication of the older immutable revision retains its original partner.
- Conversion accepts only an accepted, current, non-expired revision without a pending draft. The immutable snapshot and revision identity cross the framework-free `QuoteToInvoicePort` boundary.
- Series/revision locks plus the unique conversion row make different-key conversion races converge on one target. Operation completion, conversion row, status/timestamp, and activity are atomic. Target failure rolls back and the same key resumes.
- `LegacyInvoiceDraftAdapter` is the only new class importing the legacy `Invoice`; it creates an owner-scoped unnumbered draft with exact decimal strings, owner-validated partner reference, immutable customer data, and payment terms from settings. Canonical line fields are explicitly translated to the legacy `desc`, `qty`, `unit`, `unitPrice`, `vatRate`, `kind`, and `productId` contract. Quote discounts map as `none -> null/null`, `percent -> percent/value`, and `fixed -> amount/value`.
- The end-to-end compatibility regression proves that the stored API/editor/print shape feeds legacy reporting totals correctly and that finalizing a converted hardware invoice books the expected stock movement exactly once.
- Both legacy invoice print entry points (list row and open editor) share `buildPrintInvoice`; it now maps persisted `discount_type` and `discount_value` through the tested `legacyPrintTerms` normalization. Fixed-amount and percentage discounts therefore reach the print calculator instead of silently printing undiscounted totals.
- Review exposed a Task-8 SQLite migration rebuild regression after fixing the required delivery UUID fixture. The UUID migration now restores the delivery state enum and attempts check after both `up()` and `down()` rebuilds, with existing message identity and recipient data preserved. PostgreSQL behavior is unchanged.

## TDD evidence

- Initial RED: the new feature suite failed because `QuoteToInvoicePort` did not exist.
- Incremental GREEN: decision, duplicate, conversion, rollback/retry, foreign-owner/foreign-target, idempotency-reuse, derived-expiry, adapter, provenance, legacy reporting/editor/stock, and migration round-trip tests were added and passed.
- Review RED: canonical quote line keys produced zero legacy report totals and no stock booking; `none` remained a literal legacy discount type; duplicate target `source_type/source_id` were null; corrected delivery fixtures exposed that SQLite's UUID table rebuild had dropped state/attempt checks.
- Review GREEN: all four failures are covered by focused regressions, including invalid delivery state before `down()`, after `down()`, and after reapplying `up()` without historical row loss.
- Review round 2 RED: print snapshots forced both discount fields to null, and a discarded later-version draft left a mutable series partner that overrode the selected revision during duplication.
- Review round 2 GREEN: fixed and percentage persisted discounts produce discounted print totals, and selected-revision duplication preserves the canonical historic partner after later draft mutation/discard.
- An opt-in PostgreSQL test (`FINANCE_TEST_PGSQL_URL`) runs two different conversion keys concurrently and verifies the second worker waits on the aggregate lock and replays the one committed invoice target. It is skipped when PostgreSQL is not configured.

## Verification

- `php artisan test tests/Feature/FinanceModule/Quotes/QuoteDecisionConversionTest.php tests/Feature/FinanceModule/Quotes/QuoteSchemaTest.php tests/Feature/FinanceModule/Quotes/QuoteDeliveryTest.php tests/Feature/FinanceQuoteTest.php tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php`
- `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/FinanceModule/Quotes tests/Unit/Modules/Finance`
- `yarn test:js`
- `yarn typecheck`
- `vendor/bin/phpstan analyse` on all changed production and migration files with `--memory-limit=1G`
- `vendor/bin/pint` on all changed PHP files
- `git diff --check`

Final scoped verification:

- Focused Task 9/Task 8 schema-delivery review, legacy baseline, and legacy quote: 72 tests, 70 passed, 547 assertions, 2 environment-gated PostgreSQL concurrency tests skipped.
- Broad modular Quote feature and Finance unit suite after review round 2: 287 tests, 284 passed, 1,239 assertions, 3 environment-gated PostgreSQL tests skipped.
- Frontend: 18 test files and 360 tests passed; Vue/TypeScript typecheck passed.
- PHPStan: 0 errors across every changed production and migration file.
- Pint: passed after formatting every changed PHP file.
- `git diff --check`: passed.

Commit SHA is recorded in the handoff after commit creation.

## Binding intentionally deferred

Production resolution of `ConvertQuoteToInvoice` still needs exactly this provider binding once provider coordination permits it:

```php
$this->app->bind(QuoteToInvoicePort::class, LegacyInvoiceDraftAdapter::class);
```

No `FinanceServiceProvider` changes are part of this commit.

## Concerns

- The PostgreSQL concurrency path is executable but environment-gated; the local SQLite run validates transactional rollback, replay, owner constraints, and sequential different-key convergence.
- The legacy adapter is deliberately temporary. The later invoice module should replace the one port binding without changing Quote application commands.
- Provenance intentionally points at the durable duplicate operation rather than directly at the source series: the foundation uniqueness constraint permits only one `(owner, source_type, source_id)` target, while Task 9 explicitly permits multiple intentional duplicates of the same source under different keys. The operation owns the source-series reference and makes each copy both unique and traceable.
