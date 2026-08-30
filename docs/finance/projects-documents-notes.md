# Finance projects, documents, and notes

## Status and cutover boundary

This module is additive, exactly like `docs/finance/module-foundation.md`.
Its schema uses tables prefixed `finance_project_`, plus an additive
`supersedes_note_id` column on the foundation's `finance_document_notes`. Its
HTTP surface is `/api/v1/finance-v2/projects*` and
`/api/v1/finance-v2/document-series/{series}/notes*`, named
`api.finance-v2.projects.*` and `api.finance-v2.document-series.notes.*`.

**There is no production cutover in this plan.** The existing
`/api/v1/finance/projects*` routes, `FinanceProjectPlanController`,
`FinanceController`, `frontend/src/stores/finance.ts`, and `Finance.vue`
remain active and unchanged. `frontend/src/modules/finance/projects/routes.ts`
exports the v2 project routes but is not mounted into
`frontend/src/router/index.ts`. Cutover happens only under the gates in
"Cutover gates" below, in a separate plan.

## Schema

`finance_project_records` — owner-unique UUID, optional
`parent_project_id` (`NO ACTION`, so an archive never silently reparents
children), `business|private` kind, `planned|active|on_hold|done|cancelled`
status, optional partner reference, exact `budget_minor`, `version`,
`archived_at`, optional `source_type`/`source_id` legacy identity.

`finance_project_work_items` — per-project tasks/milestones:
`open|in_progress|done` status, `estimate_quantity_scaled` (scale-4, forbidden
on a milestone), `source_revision_id`/`source_line_index` pair (quote-derived
lines only), soft-deletable.

`finance_project_time_entries` — `quantity_scaled` (scale-4, nonzero),
`hourly_rate_minor`/`currency` frozen at log time, `invoice_target_reference`
+ `invoiced_at` set together and then immutable (no edit/delete/move/unbill).

`finance_project_ledger_entries` — `direction(out|in)`, positive
`amount_minor`, `currency`, optional category/payment-method references,
`legacy_metadata` json for unknown legacy keys.

`finance_project_document_links` — `source_type` (`finance_series`,
`legacy_invoice`, `file`, `gallery_photo`, `finance_receipt`,
`bank_transaction`, `bank_transaction_receipt`), `source_reference`, optional
`document_series_id`/`pinned_revision_id`, `role` (`source_quote`, `quote`,
`invoice`, `payment`, `receipt`, `file`, `photo`, `other`), a snapshotted
`metadata_snapshot`, `attached_at`/`attached_by`, nullable
`detached_at`/`detached_by`. Detach never deletes the row; reattaching
creates a new row.

`finance_project_notes` / `finance_project_activities` — append-only;
`type` is exactly `note|decision|call|email|meeting|correction`, only
`correction` carries `supersedes_note_id`. Runtime update/delete routes do
not exist, and `AppendOnlyRecordMutation`/`PublishedRevisionMutation` guard
every write path below the repository.

`finance_project_operations` — idempotency ledger: unique
`(user_id, operation, idempotency_key)`, `state(reserved|running|succeeded|failed)`,
stored `result` for replay.

## DTOs, commands, and queries

Application DTOs, commands, and queries live under
`App\Modules\Finance\Application\{DTOs,Commands,Queries,Ports}\Projects`.
Every command performs exactly one use case, appends its activity row inside
the same transaction, and returns the refreshed `ProjectView` (or child DTO).
Named commands only — there is no generic "update project" that can change
`status`, `parent_project_id`, owner, UUID, source identity, or archive state.

Status transitions (`ProjectWorkflow`, `App\Modules\Finance\Domain\Projects`):

```text
planned  -> active | cancelled
active   -> on_hold | done | cancelled
on_hold  -> active | cancelled
done     -> active            (explicit reopen)
cancelled -> planned          (explicit reopen)
```

Work-item transitions (`WorkItemWorkflow`): `open -> in_progress|done`,
`in_progress -> open|done`, `done -> in_progress`.

`ProjectToInvoicePort` and `ProjectFromQuoteTarget` are the two integration
seams: this module never numbers/finalizes/sends/cancels invoices and never
mutates quote status, expiry, or supersession. `LegacyInvoiceDraftFromTimeAdapter`
is a temporary compatibility adapter, to be replaced by a modular adapter in
the invoice/payments rewrite.

## Route contract

`GET|POST /finance-v2/projects[...]` per the exact surface registered in
`backend/app/Modules/Finance/Http/Routes/api.php` (project CRUD, status,
move, archive/restore, work items + reorder, time entries + invoice drafts,
totals, ledger, documents + document-sources, notes, activities) plus
`GET|POST /finance-v2/document-series/{series}/notes`. Mutations return the
current resource and `version`; a stale version returns 409
`version_conflict` with the current resource. `Idempotency-Key` is required
on create-from-quote, invoice-draft creation, attach, and detach; reuse with
different input returns 409 `idempotency_key_reused`.

## Source adapters and lock order

Seven read-only `ProjectDocumentSource` adapters
(`Infrastructure/Documents/*`) resolve/search owner-scoped evidence and
return metadata only — never `storage_path`/`blob_path`/`pdf_path`/raw
OCR/search text. `CompositeProjectDocumentCatalog` merges per-adapter pages
by `occurred_at DESC, source_type ASC, reference ASC` using a per-adapter
cursor position (no global offset, no duplicate/lost items across adapter
page boundaries).

Repository transactions lock the project, then parent/child rows when
applicable, then child rows (work/time/ledger/notes/links), then the
operation row — in that fixed order, so two concurrent writers on the same
project/parent pair cannot deadlock.

## Legacy mapping (this task)

`App\Modules\Finance\Infrastructure\Compatibility`:

- `LegacyJsonCursor` / `LegacyJsonNumber` — a minimal hand-rolled JSON
  tokenizer used only to capture a numeric lexeme as its exact source
  substring, never through `json_decode()`'s float cast.
- `LegacyProjectExpenseParser` — parses the raw `finance_projects.expenses`
  column (a free-form JSON array with no fixed row shape) into exact
  `LegacyProjectExpenseRow` values. An `amount` may be a JSON number or a
  JSON string; both are handed to `Money::fromDecimal` as raw text. More than
  two fraction digits, exponent notation, a non-array top level, a
  non-object row, a missing/zero amount, or a currency that disagrees with
  the project's currency all throw `LegacyProjectExpenseMalformed` with a
  stable `errorCode`. Unknown keys are retained verbatim under
  `legacy_metadata`. Direction is read from `direction`/`type` when present
  (`in|income` → `in`, `out|expense|outgoing` → `out`); otherwise a positive
  lexeme is `out` and a negative one is `in`, since a hand-typed "expenses"
  list is overwhelmingly spend.
- `LegacyProjectMapper::map(FinanceProject $project, string $defaultCurrency = 'EUR'): array` —
  produces one deterministic mapping plan per legacy project:
  `source_type=legacy.finance_project` / legacy id; project attributes
  (status/kind validated against the v2 enums, exact budget, partner/quote
  references, parent source id, archive state); the mutable `note` field
  mapped once into an initial internal `project_note`; tasks mapped to work
  items (a milestone with an estimate is a blocking diagnostic); time entries
  mapped with `DecimalQuantity`/`Money` (zero hours, unparseable hours/rate
  are blocking; a missing rate or an already-invoiced entry is a
  non-blocking diagnostic carrying an opaque `legacy-invoice:{id}` target);
  ledger entries from the expense parser; and document-link candidates
  scanned from `FileEntry`, `GalleryPhoto`, `FinanceReceipt`, and
  `BankTransaction` rows pointing at the project (a cross-owner pointer is a
  blocking diagnostic, never silently skipped). `LegacyProjectMapper` never
  writes to `finance_project_*` tables, never mutates the legacy row, and
  performs no bulk migration — it only returns the plan plus diagnostics for
  the global migration plan to execute. Legacy `finance_projects` has no
  `currency` column, so the caller supplies the owner's default currency;
  every mapped `amount`/`rate` is expressed in it, and a row whose own
  `currency` key disagrees is a blocking diagnostic rather than a silent
  conversion.
- `LegacyProjectDiagnostic` — `{code, blocking, message, context}`. A
  `blocking` diagnostic means the affected row must not migrate as-is.
  `LegacyProjectMapper::isBlocking($result)` is `true` iff any diagnostic in
  the result is blocking.

Deliberately out of scope for this mapper (left to the global migration
plan): resolving a legacy quote/invoice id to an immutable Finance
series/revision, and reading file/photo/receipt bytes or hashes beyond the
`sha256` column already stored on the legacy row.

## Cutover gates

```text
1. Run the global resumable migration per owner with LegacyProjectMapper.
2. Require zero blocking diagnostics and exact project/task/time/ledger/link/note counts.
3. Compare budget and ledger minor-unit totals by owner, project subtree, year, and currency.
4. Verify parent graphs are acyclic and every task/time/source/document relation is owner-valid.
5. Resolve migrated quote/invoice references to immutable Finance series/revisions and compare hashes.
6. Shadow-read legacy and v2 project/detail/document responses and compare normalized values.
7. Pause legacy project writes; rerun deltas; mount projects/routes.ts and switch the canonical alias in one cutover commit.
8. Keep rollback routing while writes are paused; never dual-write two authoritative project stores.
9. Remove Finance.vue project code, finance-store project methods, FinanceProjectPlanController, and legacy project runtime routes only in finance-legacy-removal.
```

Ownership beyond this plan: the global legacy-migration plan owns batch
orchestration, progress markers, retries, cross-module quote/invoice
reference resolution, and checksums, and calls `LegacyProjectMapper` without
duplicating its parsing/mapping rules. The frontend-cutover plan mounts
`routes.ts` and switches the canonical API alias. The legacy-removal plan
removes old project code only after the rollback/parity window closes.
