# Finance Quotes Workflow — Compatibility and Cutover Reference

This document describes the modular quote workflow built by
`docs/superpowers/plans/2026-08-28-finance-quotes-workflow.md` (Tasks 1-14): what it
persists, how it behaves, and the exact gates a later plan must clear before it
replaces the legacy `finance_quotes` runtime. It does not migrate data and does
not switch any route; those are the `finance-legacy-migration` and
`finance-frontend-cutover` plans referenced below.

## Schemas

`finance_document_series` / `finance_document_revisions` / `finance_document_activities`
/ `finance_document_notes` are the shared foundation (aggregate root, immutable
published versions, append-only activity/notes). Quote-specific state is additive:

- `finance_quote_series` — one row per series (`document_series_id` PK), owner,
  optional partner, `current_revision_id`, allocated `number`/`sequence_year`/
  `sequence_number`, optimistic `version`, and `published_at`/`accepted_at`/
  `declined_at`/`converted_at` timestamps. Soft-deletable; an issued
  `(user_id, sequence_year, sequence_number)` is unique even after soft delete,
  so a number is never reused.
- `finance_quote_drafts` — one row per series (`document_series_id` PK): the
  single mutable draft, its `based_on_revision_id` when it is a later version,
  and the authoritative `payload`/`net_minor`/`vat_minor`/`gross_minor`.
- `finance_quote_number_sequences` — `(user_id, year)` unique, `next_sequence`.
- `finance_quote_operations` — idempotency ledger: `(user_id, operation, idempotency_key)`
  unique, `state` in `reserved|running|succeeded|failed`, stored `result`/`error_code`.
- `finance_quote_deliveries` — one row per send attempt series, `(user_id, message_id)`
  unique, `state` in `queued|sending|sent|failed`, bounded `attempts`.
- `finance_quote_conversions` — `(user_id, source_revision_id, target_type)` unique,
  currently `target_type=invoice` only.

Every quote table carries `user_id` and enforces composite `(user_id, ...)`
foreign keys into `finance_document_series`/`finance_document_revisions`, so a
cross-owner reference is rejected by the database itself (PostgreSQL check
constraints in production, SQLite triggers in tests) — not only by application code.

## Commands and lock order

`Application/Commands/Quotes` holds one command per named transition:
`CreateQuote`, `UpdateQuoteDraft`, `DiscardQuoteDraft`, `StartQuoteVersion`,
`PublishQuote`, `SendQuote`, `AcceptQuote`, `DeclineQuote`, `DuplicateQuote`,
`ConvertQuoteToInvoice`. There is no generic CRUD update path; every workflow
state change goes through `QuoteWorkflow` (`Domain/Quotes/QuoteWorkflow.php`),
which raises `InvalidQuoteAction` with a stable `errorCode`.

Every command that mutates the aggregate locks rows in the same fixed order to
avoid deadlocks under concurrency: **document series → quote extension →
current/draft revision → number-sequence row**. Publication additionally
reserves the idempotency operation before taking any lock, so a retried
request after a crash resumes from the stored checkpoint instead of
re-allocating a number or re-rendering a PDF.

## Idempotency

`QuoteOperationRepository::reserve(ownerId, operation, key, requestSha256, quoteId)`
uses the `(user_id, operation, idempotency_key)` unique constraint — not an
application-level existence check — so two concurrent requests with the same
key race safely: the loser observes `operation_in_progress` (409) instead of
duplicating work. A completed reservation replays its stored `result` exactly.
The same key reused with a different request body (different `request_sha256`)
returns 409 `idempotency_key_reused`. `Create`, `publish`, `send`, `duplicate`,
`accept`, `decline`, and `convert` all require an `Idempotency-Key` header.

## API surface (`/api/v1/finance-v2/quotes*`)

```
GET    /finance-v2/quotes                              api.finance-v2.quotes.index
POST   /finance-v2/quotes/preview                      api.finance-v2.quotes.preview
POST   /finance-v2/quotes                               api.finance-v2.quotes.store
GET    /finance-v2/quotes/{quote}                       api.finance-v2.quotes.show
GET    /finance-v2/quotes/{quote}/revisions             api.finance-v2.quotes.revisions.index
PUT    /finance-v2/quotes/{quote}/draft                 api.finance-v2.quotes.draft.update
DELETE /finance-v2/quotes/{quote}/draft                 api.finance-v2.quotes.draft.discard
POST   /finance-v2/quotes/{quote}/versions               api.finance-v2.quotes.versions.store
POST   /finance-v2/quotes/{quote}/publish                api.finance-v2.quotes.publish
POST   /finance-v2/quotes/{quote}/send                   api.finance-v2.quotes.send
POST   /finance-v2/quotes/{quote}/accept                 api.finance-v2.quotes.accept
POST   /finance-v2/quotes/{quote}/decline                api.finance-v2.quotes.decline
POST   /finance-v2/quotes/{quote}/duplicate               api.finance-v2.quotes.duplicate
POST   /finance-v2/quotes/{quote}/conversions/invoice     api.finance-v2.quotes.convert.invoice
GET    /finance-v2/quotes/{quote}/revisions/{revision}/pdf api.finance-v2.quotes.revisions.pdf
```

Every route runs behind the module gate and per-route throttles, uses UUID
route keys, and returns owner-scoped 404 for a foreign identifier. `openapi.yaml`
documents the parallel `FinanceV2Quote*` schemas alongside the untouched legacy
quote contract; the legacy paths and `Finance.vue` keep working unchanged
throughout this plan and the next two.

## Error codes

`QuoteController::failure()` maps every `InvalidQuoteAction`/`DomainException`/
`InvalidArgumentException` to a stable machine code. 409 (conflict — retry after
reading `current`/reservation state) covers: `version_conflict`,
`idempotency_key_reused`, `operation_in_progress`, `quote_locked`,
`quote_revision_stale`, `quote_revision_replaced`, `quote_draft_pending`,
`quote_publication_in_progress`, `quote_delivery_in_progress`. 422
(unprocessable — the request itself is invalid) covers everything else,
including `control_totals_mismatch`, `quote_not_published`, `quote_expired`,
`quote_not_accepted`, `invalid_transition`, `invalid_money`, `invalid_quantity`,
`invalid_tax_rate`, `invalid_discount`, `invalid_validity_period`,
`invalid_customer`, `invalid_partner`, `invalid_product`, and the generic
`invalid_quote_input` fallback.

## SMTP residual risk

`DeliverQuoteRevision` retries a queued send up to 3 times with backoff
`[60, 300, 900]` seconds. If the SMTP server accepts the message but the worker
process dies before the success state is durably written, the delivery is left
in a state that records `last_error_code=delivery_outcome_uncertain` rather than
being silently retried or silently marked failed — a second identical send is
never attempted once a success is recorded for the same key, but the residual
"we don't know if the customer received it twice" risk from an at-least-once
transport is surfaced to the operator/customer-service workflow instead of hidden.

## PDF retention

A published revision's PDF is written once, to
`finance/revisions/{ownership-token-prefix}/{ownership-token}.pdf` on the
configured `files.disk`, and is never overwritten or content-deduplicated.
Its bytes, SHA-256, and `pdf_path` are immutable for the life of the revision;
only `delete($ownershipToken)` (scoped to that exact token) can remove it, and
nothing in this plan calls that path for a published revision. Streaming is
authorized, owner-scoped, sends `nosniff` + a sandboxed CSP, and is cached as
`private, immutable`.

## Legacy compatibility (Task 13)

`Infrastructure/Compatibility/LegacyQuoteMapper` is a pure, side-effect-free,
deterministic per-row translator from one legacy `App\Models\FinanceQuote` row
to the shape a later migration writes as a `source_type=legacy.finance_quote`,
`source_id={legacy id}` aggregate. It performs **no writes** and runs **no bulk
migration** — that orchestration belongs entirely to `finance-legacy-migration`,
which calls this mapper per row and must not duplicate its rules.

Mapping rules:

- An unnumbered legacy row (`number IS NULL`) becomes a mutable draft
  (`kind: 'draft'`) with an authoritatively recalculated `net_minor`/`vat_minor`/
  `gross_minor` and a draft payload shaped exactly like `QuoteDraftFactory`'s
  output (so the later migration can hand it straight to the same persistence
  layer Task 4/5 already built).
- A numbered row becomes exactly one published revision (`kind: 'published'`,
  `revision_number: 1`) preserving the legacy `number`/`year`/`seq`, its
  `sent_at` (falling back to `created_at`) as `published_at`, its original
  `pdf_path`, and a freshly verified SHA-256 of the actual stored PDF bytes —
  the legacy row's own recorded hash (it has none) is never trusted.
  `series_uuid` in the returned snapshot is `null`: minting the aggregate's UUID
  is the later migration's job, since this mapper never writes anything.
- `converted_invoice_id`/`converted_project_id` become **unresolved** external
  references (`resolved: false`, `target_id: null`) tagged `legacy-invoice:{id}`/
  `legacy-project:{id}`; the later invoice/project migrations are the ones that
  resolve them to a real `finance_quote_conversions` row.
- Soft-deleted and expired-by-date rows map exactly like any other row: `deleted_at`
  is forwarded as data, and expiry is derived at read time by the existing
  `QuoteWorkflow` rules — nothing here writes a stored "expired" state.
- Legacy decimal strings and JSON numeric tokens are converted through the same
  `Money`/`DecimalQuantity`/`DocumentCalculator` the live commands use, so no
  float ever enters the pipeline. A legacy value that cannot round-trip exactly
  — unsupported scale, out-of-range, a currency that is not a 3-letter code, a
  partner/product the owner does not own, a stored total that does not match
  the exact recalculation, a missing/unsafe/non-PDF stored file — returns a
  blocking `LegacyQuoteDiagnostic` (`code` drawn from a fixed vocabulary) instead
  of a mapped row. `LegacyQuoteMapperTest` exercises every one of these gaps
  plus draft/sent/accepted/declined/expired/soft-deleted/converted happy paths
  and confirms mapping the same row twice is byte-identical.

## Cutover prerequisites (owned by later plans, listed here as the gate)

1. Run the global resumable migration per owner using `LegacyQuoteMapper`.
2. Require zero blocking diagnostics and exact counts/net/vat/gross by
   owner/year/currency.
3. Verify every numbered legacy quote has the same number, PDF SHA-256, status,
   partner/product ownership, and conversion reference in the new aggregate.
4. Shadow-read legacy and v2 list/detail responses and compare normalized values.
5. Mount `frontend/src/modules/finance/quotes/routes.ts` and switch its API base
   from `/finance-v2/quotes` to the canonical `/finance/quotes` alias in one
   cutover commit.
6. Replace legacy quote route registrations; keep rollback routing available
   while new writes are paused, and never dual-write two authoritative quote stores.
7. Remove `Finance.vue` quote code, finance-store quote methods,
   `FinanceQuoteController`, legacy `QuoteMail`, and legacy runtime routes only
   in `finance-legacy-removal`.

None of steps 1-7 run in this plan. This plan only proves the mapping function
those steps will call is deterministic and exhaustive over the legacy fixture
space.

## Verification record

See the "Verification" section of `.superpowers/sdd/quote-task-14-report.md`
for the dated command output, test/assertion counts, and quality-gate results
this plan's Task 14 recorded.
