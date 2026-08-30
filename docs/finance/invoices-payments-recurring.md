# Finance invoices, payments, and recurring invoices

## Status and cutover boundary

This is a **completed cutover** for invoices, payments, and recurring
invoices, with one narrow, deliberate exception. As of this document:

- Every invoice write (create, finalize, cancel, deliver, remind) goes
  through the finance-v2 module at the canonical routes
  `/api/v1/finance/{invoices,payments,payment-allocations,
  recurring-invoice-templates,recurring-invoice-runs}`. The legacy
  `FinanceController` invoice CRUD/finalize/storno/email/dun/PDF-upload
  methods and routes are deleted; the legacy `invoices` table and
  `App\Models\Invoice` model remain solely as historical record.
- Quote conversion (both the legacy `FinanceQuoteController` screen and
  project-time billing, both the older `FinanceProjectPlanController` path
  and the newer hexagonal Projects module) creates finance-v2 invoice
  drafts via `CreateInvoiceDraftFromSource`, never a legacy `invoices` row.
- One legacy invoice-writer path is **deliberately untouched**: the new
  Quote module's own conversion endpoint (`POST /quotes/{quote}/
  conversions/invoice`) still writes a legacy `invoices` row via
  `LegacyInvoiceDraftAdapter` (bound to `QuoteToInvoicePort`). This is out
  of this plan's scope — that quote module is itself still on the preview
  `finance-v2` prefix, not yet cut over — and is documented as a known,
  bounded follow-up, not an oversight.
- One read-only exception survives on both `routes/api.php` and
  `routes/web.php`: `FinanceController::legacyInvoicePdf()` streams a
  pre-cutover invoice's own already-stored PDF (and, per its GoBD
  correction trail, one historical version's own PDF). GoBD requires an
  already-issued invoice's document stay reachable for the statutory
  retention period; losing that would be a compliance regression the
  cutover must not cause. No write route exists alongside it.
- `LegacyInvoiceReadProjection` feeds `FinanceController::snapshot()` (the
  Home/`finance.data` endpoint) and every `FinanceReports` method
  (`realizedInvoices`, `aging`, `vatAdvanceReturn`) with the union of
  legacy and finance-v2 invoices, materialized as non-persisted `Invoice`
  model instances (negative ids, so they can never collide with a genuine
  legacy row) so the existing, already-audited per-line
  totals/discount/VAT-rate computation runs unmodified over both sources.

## Request/resource shapes

### Invoice draft (`POST /invoices`, request body)

```json
{
  "issue_date": "2026-08-28", "due_date": "2026-09-11", "currency": "EUR",
  "customer": { "name": "ACME", "email": "billing@example.test", "invoiceEmail": "ap@example.test" },
  "partner_id": null, "project_id": null,
  "lines": [{
    "description": "Consulting", "quantity": "2.5000", "unit": "h",
    "unit_price": "100.00", "tax_rate": "19.00", "kind": "service", "product_id": null
  }],
  "discount_type": "none", "discount_value": null
}
```

- `quantity` is a canonical scale-4 decimal string (`-?(?:0|[1-9]\d*)\.\d{4}`).
- `unit_price`/`tax_rate`/`discount_value` are scale-2 decimal strings.
- `discount_type` is one of `none|percent|fixed` (NOT the legacy
  `none|percent|amount`).
- `customer.invoiceEmail` is optional; when present it is the delivery
  recipient's preferred address (see Delivery below) — added specifically
  during this cutover to carry forward a legacy behavior finance-v2 was
  missing (`EloquentInvoiceRepository::assertDeliveryReady()`).
- The server always recomputes totals from `lines`/`discount`; `control_net_minor`/
  `control_vat_minor`/`control_gross_minor` (optional, exact-integer strings) are
  asserted against that recomputation and cause `document_totals_mismatch` on
  mismatch — used by the source contract (quote conversion, project-time
  billing, migration) to prove the caller's own totals agree with the
  server's, never trusted blindly from a client.

### Invoice resource (response)

Every money/count field is serialized as an **exact-integer string** (never
a JSON number, to survive JS's 53-bit safe-integer ceiling and any client's
float-based JSON parser): `net_minor`, `vat_minor`, `gross_minor`,
`allocated_minor`, `open_minor`, `version`. `id` is the invoice's own uuid;
`number` is `null` until finalized.

## Source contract

Every invoice, regardless of origin, is created through exactly one entry
point: `CreateInvoiceDraftFromSource::handle(InvoiceDraftSource $source,
IdempotencyKey $key)`. `InvoiceDraftSource` is
`(sourceType, sourceKey, sourceRevisionId, sourceSnapshotSha256, InvoiceDraftData $draft)`.
`sourceType` is one of:

| `sourceType` | Producer | `sourceKey` |
| --- | --- | --- |
| `quote_revision` | (not yet live — see cutover boundary above) | quote id |
| `legacy_quote_snapshot` | `LegacyQuoteInvoiceSource` (old `FinanceQuoteController`) | legacy quote id |
| `project_time_batch` | `LegacyProjectTimeInvoiceSource` (Projects module) / `LegacyProjectPlanInvoiceSource` (old `FinanceProjectPlanController`) | deterministic hash of project + claimed time-entry uuids |
| `recurring_run` | `ProcessRecurringInvoiceRun` | template id + scheduled occurrence |
| `cancellation` | `EloquentInvoiceRepository::createCancellationDraft()` | the invoice being cancelled |
| `legacy_invoice` | `ImportLegacyInvoice` (migration only) | legacy invoice id |

`(user_id, source_type, source_key)` is unique: a second call with the same
key and an unchanged snapshot hash replays the first result; a changed hash
throws `source_snapshot_conflict`. No Invoice command ever reads or mutates
the producer's own aggregate (a Quote, a Project) — the producer freezes an
immutable snapshot and hands it over once.

## Status projection

`workflow_status` on `finance_invoices` is one of `draft|finalized|sent|
cancelled`. The **displayed** status a client sees is derived, not stored,
by `App\Modules\Finance\Domain\Invoices\InvoiceBalance::effectiveStatus()`:

1. `cancelled` if a cancellation invoice references this one.
2. Else, if `allocated_minor === 0`: `sent` if a delivery has gone out,
   else `finalized`.
3. Else, if `open_minor === 0` (or the open balance flipped sign, i.e.
   overpaid): `paid`.
4. Else: whatever the raw `workflow_status` is (partially paid, still
   open).

Payment and delivery are independent: an invoice can become `paid` without
ever being formally sent, and delivering it does not need a payment.
`LegacyInvoiceReadProjection::legacyStatus()` mirrors this exact rule (not
the legacy model's "paid only follows sent" assumption) so a report never
disagrees with what the invoice API itself would say.

## Number allocator

`LockedInvoiceNumberAllocator` assigns `(number, year, sequence)` at
finalize time, inside the same transaction: it locks the owner's numbered
rows for the target year, takes `max(sequence)+1` (never below the
configured floor), and writes atomically, so two concurrent finalizations
can never mint the same number. A unique-constraint race on the very first
number of a new year retries a bounded number of times before surfacing a
409. Once assigned, a number is never reused, even across a cancelled or
migrated row — see Migration/control totals below for how a migrated
historical number is *preserved*, never *reallocated*.

## Signed payment allocation

A `finance_payments` row carries a signed `amount_minor` (negative =
refund). `AllocatePayment` distributes some or all of a payment's
unapplied balance across one or more invoices in one batch
(`finance_payment_allocation_batches` + `finance_payment_allocations`),
each allocation itself signed and matching the invoice's currency and open
balance's sign — an allocation can partially cover an invoice, and a
reversed allocation (`reverses_allocation_id`) exactly negates a prior one
rather than being deleted, preserving the full audit trail.
`InvoiceBalance`'s overpayment guard (`allowOverpayment`) only relaxes the
"open balance keeps the invoice's sign" check where a specific allocation
explicitly opts in.

## Cancellation / refund

`EloquentInvoiceRepository::createCancellationDraft()` (`sourceType=
'cancellation'`) creates a NEW invoice with `kind='credit_note'`,
`cancels_invoice_id` set, the same customer/partner, and every line's
`unit_price_minor` and the discount both exactly negated — so the
cancellation is a mathematically exact reverse of the original, including
a fixed or percent discount. The original invoice is never edited, never
deleted; only a finalized, non-credit-note, not-already-cancelled invoice
may be cancelled, and a credit note itself can never be cancelled
(`credit_note_cannot_be_cancelled`). Cancelling a paid invoice does not
fabricate a refund payment — the credit note's own negative gross is the
record; an actual money movement is a separate, real `finance_payments`
row with a negative `amount_minor` if and when the refund is actually
paid out.

## PDF storage, hash, and streaming

Every finance-v2 document PDF is server-rendered (`BladeDocumentRenderer`
via dompdf, deterministic — a fixed `CreationDate`/`ModDate` and a content
hash-derived `fileIdentifier` so re-rendering an unchanged snapshot
produces byte-identical output) and stored at
`finance/revisions/{sha256[0:2]}/{sha256}.pdf` on the atomic document
object store, with the sha256 recorded alongside on
`finance_document_revisions`. Streaming
(`InvoiceRevisionController`/`api.finance-v2.invoices.revisions.pdf`) is
owner-scoped, served with `Content-Security-Policy: default-src 'none';
sandbox` and `X-Content-Type-Options: nosniff`. There is no client-writable
PDF path anywhere in finance-v2 — eliminating the entire IDOR class the
legacy `versions[].pdf` client-supplied path carried. The one narrow legacy
survivor, `FinanceController::legacyInvoicePdf()`, streams a pre-cutover
invoice's *already-stored* blob under the exact same headers; it never
accepts an upload.

## Delivery `failed`/`unknown` semantics

`InvoiceDeliveryController::send()`/`remind()` queue a delivery
(`finance_invoice_deliveries`), idempotent per `Idempotency-Key`.
`assertDeliveryReady()` resolves the recipient as: explicit `recipient`
parameter, else `customer.invoiceEmail` if present and non-empty, else
`customer.email` — this precedence was added in the Task 17 cutover to
carry forward the legacy behavior finance-v2 was missing. A delivery
transitions `pending → sending → sent`, or `sending → failed` on a
transport error the mailer can characterize as safe to retry (a real
non-acceptance from the SMTP server), or `sending → unknown` when the
outcome is genuinely uncertain (a connection dropped after the transport
layer may have already accepted the message) — `unknown` deliberately
never auto-retries, because retrying an uncertain send risks a duplicate
customer email; only an explicit `RetryInvoiceDelivery` call (a new
delivery attempt, not a resend of the same one) recovers it. A worker
sweep only advances `unknown` after the immutable PDF's digest is
re-verified.

## Recurring invoice scheduler: month-end, timezone, catch-up

`finance:run-recurring-invoices` runs every minute (`withoutOverlapping`,
`onOneServer`), claims every due occurrence across all owners in one
bounded transaction (global cap 1,000, 100 per template per tick), and
advances `next_run_at` only by the occurrences actually claimed — a run
that falls behind (e.g. after downtime) catches up over several ticks
without ever skipping or duplicating an occurrence. Month-end intervals
(e.g. "the 31st of every month") clamp to the shorter month rather than
rolling into the next one. All scheduling arithmetic happens in the
template's own configured timezone, converted to UTC only for storage.

`ProcessRecurringInvoiceRun` advances a run by exactly one step per
invocation, resuming from `last_completed_step` (not the raw `status`) so
a `RetryRecurringInvoiceRun` after a failure continues from exactly where
it stopped rather than re-attempting an already-completed step (e.g.
re-finalizing after the delivery step alone failed).

## Job attempts and backoff

`ProcessRecurringInvoiceRunJob` is `ShouldBeUnique` per run uuid, 5
attempts, backoff `[60, 300, 1800, 7200]` seconds. `Auth` is set per job
via `Auth::onceUsingId()` and restored in a `finally` block (never a blanket
`forgetGuards()`), so a worker processing jobs for different owners in
sequence never leaks one owner's context into the next job.

## Migration commands and control totals

`finance:migrate-invoice-slice {--user=*} {--all-owners}` resumably
migrates legacy invoices, bank-transaction payment links, and `paid`-marker
residual payments to finance-v2, via the same
`ImportLegacyInvoice`/`RecordPayment`/`AllocatePayment` commands an
interactive user would call — never a raw insert into a `finance_*` table.
Historical invoice numbers, sequences, and PDF bytes are *preserved
exactly* (via `InvoiceRepository::importFinalized()`), never reallocated
or re-rendered; no inventory movement is recorded for a migrated
finalization (that already happened, historically, once). Progress is
tracked per-owner in `finance_invoice_migration_checkpoints`; every
underlying write is independently idempotent, so a re-run after an
interruption never duplicates regardless of where the checkpoint left off.

`InvoiceControlTotals::compare(int $ownerId)` recomputes legacy vs.
migrated invoice count and net/VAT/gross totals (in minor units) per owner
and reports an exact mismatch list on disagreement.
`finance:check-invoice-cutover` (`InvoiceCutoverCheck::run()`) is the
release gate: an owner is `ready` only when their migration checkpoint is
`complete` **and** their control totals match exactly; the command exits
non-zero if any owner is not ready. **Running the real migration against
production data is a deployment-time operation, deliberately never
exercised against real user data by an automated session** — it is
proven correct here purely against test fixtures
(`tests/Feature/FinanceModule/LegacyInvoiceMigrationTest.php`, 7/7
passing, including a full run of both artisan commands end to end).

## Rollback boundary

- **Code**: every commit in this cutover is a normal, revertible git
  commit; there is no destructive schema change — every new column
  (`converted_finance_invoice_id`, `invoiced_finance_invoice_id`) is
  purely additive alongside the legacy column it complements, so reverting
  the code changes nothing about data already written.
- **Data already migrated**: `finance:migrate-invoice-slice` is one-way by
  design (GoBD forbids un-issuing an invoice number). There is no "unmigrate"
  command. Before running it against real data, take a database backup;
  the migration itself is safe to interrupt and resume, but is not safe to
  partially undo by hand.
- **Legacy writer paths**: deleting them is the actual point of no return
  for this cutover — after this deploy, nothing can create a new legacy
  `invoices` row (bounded exception noted above). If a rollback is needed
  before the next deploy, redeploying the previous release restores the
  legacy routes; any finance-v2 invoices created in the meantime remain
  valid finance-v2 data (they are never migrated backward into `invoices`).
