# Invoice plan Task 6 report

Date: 2026-08-28
Branch: `codex/finance-module-rewrite`

## Status

Implemented Task 6's exact invoice-draft Application boundary and the single behavior-independent external source contract using test-first red/green slices. The Finance service provider was intentionally not changed because Quote Task 7 owns concurrent provider work; tests bind the invoice ports explicitly.

## Delivered

- Exact `InvoiceLineData` and `InvoiceDraftData` validation: canonical scale-4 quantities, integer minor units, bounded tax basis points, canonical line kinds, positive optional IDs, recursive float-free customer JSON, canonical currency, and control totals that can only validate server-calculated values.
- Canonical revision snapshots containing deterministic customer objects, scaled quantities, integer prices/rates, discount inputs, exact totals, currency, and sorted tax breakdowns. Client-supplied control totals never enter the stored snapshot or source request fingerprint.
- `CreateInvoiceDraft`, `UpdateInvoiceDraft`, and `DeleteInvoiceDraft` commands over the Invoice repository port. Updates and deletes use owner-scoped row locks and exact version CAS. Creation/update append `invoice.draft.created` and `invoice.draft.updated`; deletion removes the complete unpublished manual-draft aggregate.
- Owner validation for partner, legacy project compatibility, and product references on create/update/source creation. Foreign or soft-deleted references fail as `ModelNotFoundException` without partial writes.
- Immutable `InvoiceDraftSource` with the allowed identifier-only labels `quote_revision`, `legacy_quote_snapshot`, `project_time_batch`, `recurring_run`, `cancellation`, and `legacy_invoice`; no Quote class, port, enum, repository, or behavior switch is imported.
- `CreateInvoiceDraftFromSource` backed by the shared hash-only idempotency store, an owner row lock, owner-scoped source uniqueness, a canonical internal request fingerprint, and one outer transaction covering reservation, series/revision/invoice/activity creation, and completion.
- Identical source calls replay the same invoice. Changed source metadata or invoice payload under the same source identity raises `source_snapshot_conflict`; a reused operation key with a different request retains the shared stable idempotency error. Source metadata survives later draft edits.
- Source-created drafts cannot be hard-deleted because that would destroy the immutable replay identity. They remain editable while draft, but their frozen source metadata/request fingerprint is preserved.
- `InvoiceView` now returns partner/project references, canonical snapshot data, and frozen source metadata. Draft creation never allocates a number, publishes a revision, writes PDF metadata, or advances workflow.

## TDD evidence

Observed failing tests before each corresponding production slice for:

1. Missing manual create command.
2. Missing canonical snapshot in `InvoiceView`.
3. Nested customer floats crossing the JSON boundary.
4. Stable `document_totals_mismatch` control-total rejection.
5. Foreign partner/project/product resolution.
6. Missing update command and missing winner activity.
7. Missing draft delete command.
8. Missing source DTO and source command.
9. Invalid source labels/keys/revision IDs/hashes.
10. Same source identity accepting a changed draft.
11. Draft update erasing frozen source metadata.
12. Control totals incorrectly changing source idempotency identity.
13. Arbitrary invoice line kinds.
14. Missing partner/project fields in the read model.
15. Source-draft deletion breaking idempotent replay.

Negative coverage also pins stale/finalized deletion, foreign update/source references, float money rejection, absence of client workflow/number/PDF authority, cross-owner source results, and rollback after an injected database failure during idempotency completion.

## Verification

- `php artisan test tests/Feature/FinanceModule/InvoiceDraftApplicationTest.php tests/Feature/FinanceModule/InvoiceSourceContractTest.php tests/Feature/FinanceModule/FinanceInvoicePersistenceTest.php tests/Feature/FinanceModule/DocumentCoreSchemaTest.php tests/Feature/FinanceModule/InvoiceSchemaTest.php`
  - PASS: 116 tests discovered, 113 passed, 596 assertions, 3 optional PostgreSQL skips.
- Pint over the exact Task 6 Application/repository/test allowlist
  - PASS.
- PHPStan over the exact Task 6 Application/repository allowlist with `--memory-limit=1G --no-progress`
  - PASS: 0 errors.
- `git diff --check`
  - PASS.
- Static scope review for Quote imports in the Task 6 production/test paths
  - PASS: no Quote dependency.

## Integration bindings required later

The provider still needs these bindings from the coordinating integration task:

- `IdempotencyStore::class => EloquentIdempotencyStore::class`
- `InvoiceRepository::class => EloquentInvoiceRepository::class`

`Clock` is already bound by the current provider. The new commands can then be constructor-resolved automatically.

## Concerns and follow-up

- Source ownership/eligibility is intentionally a precondition supplied by the source module. `sourceRevisionId` is opaque across quote revisions, legacy snapshots, project batches, recurring runs, cancellations, and imports, so Invoice code cannot query one source table without introducing forbidden behavior switches. The invoice boundary enforces the authenticated owner, owner-scoped source uniqueness/result lookup, and all invoice-side partner/project/product references.
- The PostgreSQL-specific skips in the combined regression command are existing optional schema/persistence paths gated by `FINANCE_TEST_PGSQL_URL`; Task 6 adds no database schema or PostgreSQL-only SQL.
- No `FinanceServiceProvider` change, Quote import, push, tag, deploy, merge, or unrelated file mutation was performed.
