# Quote Task 6 Report

## Status

Task 6 from `docs/superpowers/plans/2026-08-28-finance-quotes-workflow.md` is implemented on the `finance-module-rewrite` worktree. The change adds owner-scoped numbering, immutable first and later Quote publications, in-series draft versions, canonical snapshots, and resumable publication checkpoints. It does not add a production renderer, storage adapter, HTTP surface, mail, push, tag, or deployment.

## Design and scope

- `CanonicalDocumentSnapshot` now owns the Foundation command's recursive JSON canonicalization and authoritative line/total replacement. The existing `CreateDocumentRevision::handle(CreateRevisionData)` signature remains compatible.
- Foundation revision creation gained an additive idempotent method. A hashed internal creation key is stored with `revision.created`, inside the same series-locked transaction as the revision. A crash before the Quote operation checkpoint can therefore rediscover the exact revision and verify its snapshot hash.
- `QuoteNumberAllocator` is an Application port. `DatabaseQuoteNumberAllocator` is the only adapter and the only place that imports legacy `DocumentNumber`. It uses the owner/year sequence row, configured floor/template, all persisted Quote numbers including soft-deleted rows, and owner-wide display-number collision checks.
- `PublishQuote` reserves the existing owner/operation/key record and stores durable revision/PDF checkpoints. A same-key retry resumes the same revision, calls Foundation publication idempotently, and finalizes the Quote only once.
- Quote aggregate writes remain in `EloquentQuoteRepository`. Lock order is series, Quote extension, current revision, draft, then operation/target revision as applicable. Number assignment and the sequence increment share one transaction. Finalization updates the current pointer/root state/version, removes the draft, and appends activities atomically.
- `StartQuoteVersion` delegates to the repository transaction, copies the immutable current snapshot into a separate draft with `based_on_revision_id`, preserves the sent current revision, and rejects pending drafts or terminal series.
- The single necessary provider change binds `QuoteNumberAllocator` to `DatabaseQuoteNumberAllocator`. No renderer/storage production binding was added because that belongs to Task 7.
- `PublishDocumentRevision` required no behavior change: its existing series/revision lock, idempotent published-revision replay, ownership-token cleanup, and path/hash persistence already satisfy the Task 6 PDF checkpoint contract.

## Behavior delivered

- The first publication uses the owner's issue-date year, configured `quote_number_format`, and `quote_next_number` floor.
- Sequence and rendered-number collisions are skipped. Soft deletion never releases a number, and transaction rollback restores an uncommitted allocation.
- A transaction that loses a number race retries only the two known Quote number constraints; unrelated integrity failures are not swallowed.
- Two independently published series receive distinct numbers. A reused publish key with changed version/reason/series input raises `idempotency_key_reused`.
- Published snapshots contain only the planned top-level fields: `schema_version`, Quote type/series/number/revision identity, title/customer/partner, dates/currency, authoritative lines/discount/totals, customer-facing texts, and `customer_note`. Internal notes are excluded.
- Quote line snapshots preserve server-validated display strings and metadata while numeric quantities, prices, tax rates, and all totals are replaced from domain values.
- Initial publication performs `draft -> sent`; later publication stays `sent -> sent`, keeps the base number, generates `-R2`, links revision 2 to revision 1, and leaves revision 1 snapshot/path/hash/bytes unchanged.
- `quote.published` is written once. Later publication additionally appends `quote.revision.superseded` with previous and current revision IDs.
- Validation failure allocates no number. Revision creation, renderer, storage, revision-checkpoint, and final aggregate failures are retryable without duplicate committed numbers, revisions, successful renders/files, or final activities.
- Replaying an older completed publication after a later revision returns the current Quote resource without re-rendering or mutating history.

## TDD evidence

1. Canonical builder RED: `CanonicalDocumentSnapshot` did not exist. GREEN extracted Foundation behavior without changing existing snapshot results.
2. Revision recovery RED: `CreateDocumentRevision::handleIdempotently()` did not exist. GREEN created/replayed one series revision and rejected creation-key reuse with changed canonical content.
3. Version RED: `StartQuoteVersion` was not container-resolvable. GREEN copied the current immutable snapshot and preserved its revision/PDF.
4. Numbering RED: `QuoteNumberAllocator` did not exist. GREEN honored format/floor and skipped a deleted number.
5. Publication RED: `PublishQuote` did not exist. GREEN completed one numbered canonical revision and same-key replay with one render/store.
6. Validation-order RED: an invalid discount still allocated `AN-2026-0001`. GREEN performs full domain draft parsing before number assignment.
7. Historical replay RED: replaying publication 1 after publication 2 threw because the current pointer had advanced. GREEN validates the stored identity but returns the current aggregate without another side effect.
8. Quote-line contract RED: canonical revisions dropped unit/kind/product and exact display strings. GREEN preserves validated metadata while Foundation replaces authoritative numeric fields.
9. Display-number race RED: a prepared Quote hit the owner/number unique constraint. GREEN retries the locked preparation only for the known owner-number/owner-sequence constraints and persists the next allocation.
10. Immutable-current RED: versioning and later publication accepted a mutable current revision. GREEN requires the current pointer to reference a revision with `status=published` and `published_at` before either transition.

## Verification

- `php artisan test tests/Feature/FinanceModule/Quotes/QuotePublicationTest.php tests/Feature/FinanceModule/DocumentRevisionApplicationTest.php`
  - PASS: 42 tests, 235 assertions.
- `php artisan test tests/Feature/FinanceModule/Quotes`
  - PASS: 67 passed, one opt-in PostgreSQL test skipped, 475 assertions.
- `php artisan test tests/Feature/FinanceModule/DocumentRevisionApplicationTest.php tests/Feature/FinanceModule/DocumentPersistenceTest.php tests/Feature/FinanceModule/FinanceModuleBootstrapTest.php`
  - PASS: 60 tests, 224 assertions.
- PHPStan over the complete Finance module
  - PASS: zero errors with a 1 GiB analysis limit.
- Focused Pint `--test` over every changed Task 6 production/test file
  - PASS after formatting.

## Remaining concerns

The configured local suite uses SQLite. It exercises real unique constraints, row-backed allocation, rollback, collision, and resumable failure paths, while the existing Quote PostgreSQL operation-conflict test remains opt-in. No PostgreSQL service credentials were supplied for this run.

No push, tag, deployment, PR, mail, production renderer/storage, or integration action was performed.
