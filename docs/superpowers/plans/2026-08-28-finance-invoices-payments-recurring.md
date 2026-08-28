# Finance Invoices, Payments, and Recurring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the owner-scoped invoice, payment-allocation, dunning, cancellation, and recurring-invoice slice of the new Finance module with exact money, immutable finalized revisions/PDFs, idempotent background processing, and a verified migration/cutover path.

**Architecture:** Invoice content lives in the existing document-series/revision core; invoice rows contain workflow, numbering, source-link, and balance projections, while immutable revisions remain the authoritative document snapshots. Payments and allocations form an append-only signed ledger, recurring templates select immutable effective-dated versions, and all HTTP controllers call one Application command or query through module ports. Development is additive under `finance-v2`; the existing `/api/v1/finance/invoices` runtime and monolithic Vue screen are switched only after legacy migration controls and all legacy invoice writers pass the cutover gate.

**Tech Stack:** PHP 8.5, Laravel 13.8, Eloquent, PostgreSQL 18 production, SQLite tests, queued Laravel jobs/scheduler, Blade plus `dompdf/dompdf` for server-side PDFs, PHPUnit 13, PHPStan/Larastan, Pint, Vue 3.5, TypeScript 6, Pinia 4, Vue Router 5, Vitest 4, ESLint 10, OpenAPI 3.1.

**Spec:** `docs/superpowers/specs/2026-08-28-finance-module-rewrite-design.md`

## Global Constraints

- Target release remains `1.785.0`; this plan does not push, tag, deploy, or bump the application version.
- Authoritative money uses integer minor units and quantities use `DecimalQuantity`; Domain and Application code must not perform floating-point arithmetic.
- The client sends editable inputs, never authoritative totals. Optional control totals must match the server result exactly or return HTTP 422 with code `document_totals_mismatch`.
- Domain depends only on PHP and Finance Domain classes; Application depends only on Domain and ports; Infrastructure implements persistence, PDF, mail, and scheduling; HTTP owns validation and Resources only.
- Every record is directly owner-scoped or resolved through a composite owner-validated aggregate relation. A foreign owner's identifier always produces 404.
- Generic create/update requests cannot set invoice workflow, settlement, numbering, PDF, delivery, cancellation, allocation, or recurring-run state.
- A finalized invoice revision and its PDF path/hash are immutable. Corrections create a separately numbered cancellation/credit document; they never rewrite the original.
- Invoice status is projected from workflow, successful delivery, allocations, and cancellation relation; callers cannot set `partially_paid`, `paid`, or `cancelled`.
- Invoice numbering uses a per-owner/per-year locked sequence row plus database uniqueness. A failed finalization rolls back the number and every required database effect.
- External/retriable actions accept `Idempotency-Key`; the same key and payload replays the prior result, while the same key with a different payload returns 409 `idempotency_conflict`.
- SMTP is at-least-once: a stable Message-ID and explicit `unknown` state make the unavoidable “accepted by SMTP, worker died before commit” boundary visible instead of claiming exactly-once delivery.
- Recurring catch-up never silently skips a due occurrence. Each scheduler tick is bounded, and later ticks continue from the persisted `next_run_at`.
- Existing legacy Finance routes, tables, tests, and UI remain functional until the explicit cutover task; historical migrations are never edited or deleted.
- Every new `/api/v1` route is authenticated, device-ability checked, two-factor checked, finance-gated, throttled, owner-tested, and documented in `openapi.yaml` in the same commit.
- Every backend change has a PHPUnit test. Frontend changes pass Vitest, `vue-tsc`, ESLint, and the production build.

## Scope and fixed decisions

- Invoice effective states are `draft`, `finalized`, `sent`, `partially_paid`, `paid`, and `cancelled`. `cancelled` wins over settlement; `paid`/`partially_paid` win over the underlying `finalized`/`sent` workflow state.
- Payment and allocation amounts are signed minor units. Incoming money is positive; refunds/outgoing money are negative. An invoice has positive gross, its cancellation document has negative gross, and `open_minor = gross_minor - sum(active allocation entries)` works for both.
- Allocation corrections are append-only reversals. Existing allocations are never updated or deleted.
- Overdue means: not cancelled, sent at least once, due date before the owner's local date, and `open_minor > 0`. A partially paid invoice remains overdue for its remainder.
- Supported recurrence intervals are exactly `monthly`, `quarterly`, `semiannual`, and `annual`; the schedule preserves the original day or month-end anchor in the template timezone.
- `draft` recurring mode stops after draft creation. `auto_send` resumes through draft creation, finalization/PDF, delivery staging, and mail completion without repeating a completed step.
- Quote-to-invoice conversion enters only through `CreateInvoiceDraftFromSource`. Invoice code imports no Quote class, repository, status enum, or controller. Quote eligibility and “accepted/current/not expired” checks remain owned by the quote module.
- Deposit, progress, final invoices, delivery notes, order confirmations, a customer portal, automatic payment confirmation, and automatic allocation without user approval remain out of scope.

## File and responsibility map

### Backend Domain and Application

- `backend/app/Modules/Finance/Domain/Invoices/*`: invoice kind, effective-status projection, workflow guards, balance invariants, and stable domain error codes.
- `backend/app/Modules/Finance/Domain/Payments/*`: signed allocation ledger and remaining-payment/invoice-balance rules.
- `backend/app/Modules/Finance/Domain/Recurring/*`: interval, effective template version, month-end, leap-year, and timezone schedule rules.
- `backend/app/Modules/Finance/Application/DTOs/Invoices/*`: exact draft input, source contract, invoice identifiers, resource read models, and finalize/delivery results.
- `backend/app/Modules/Finance/Application/DTOs/Payments/*`: payment, allocation, reversal, suggestion, and balance DTOs.
- `backend/app/Modules/Finance/Application/DTOs/Recurring/*`: template version, occurrence, and run result DTOs.
- `backend/app/Modules/Finance/Application/Commands/Invoices/*`: draft, finalization, delivery, reminder, cancellation, and source conversion use-cases.
- `backend/app/Modules/Finance/Application/Commands/Payments/*`: record payment, allocate, and reverse use-cases.
- `backend/app/Modules/Finance/Application/Commands/Recurring/*`: template versioning, pause/resume, run claiming, and retry use-cases.
- `backend/app/Modules/Finance/Application/Queries/*`: paginated invoice/payment/template/run queries, invoice detail/history, aging, and server-side payment suggestions.
- `backend/app/Modules/Finance/Application/Ports/*`: invoice/payment/recurring repositories, numbering, inventory, idempotency, delivery, clock, and source-link boundaries.

### Backend Infrastructure and HTTP

- `backend/database/migrations/2026_08_28_110000_create_finance_invoices.php`: invoice, sequence, delivery, and idempotency schema.
- `backend/database/migrations/2026_08_28_110100_create_finance_payments.php`: payments, allocation batches, and append-only allocation entries.
- `backend/database/migrations/2026_08_28_110200_create_finance_recurring_invoices.php`: templates, versions, and runs.
- `backend/database/migrations/2026_08_28_110300_create_finance_invoice_migration_support.php`: resumable legacy checkpoints and control totals.
- `backend/app/Modules/Finance/Infrastructure/Persistence/Models/*`: guarded owner-scoped records.
- `backend/app/Modules/Finance/Infrastructure/Persistence/Eloquent*Repository.php`: locks, transactions, projections, uniqueness retry, and append-only writes.
- `backend/app/Modules/Finance/Infrastructure/Pdf/BladeDocumentRenderer.php`: deterministic server-side invoice PDF bytes from canonical snapshots.
- `backend/app/Modules/Finance/Infrastructure/Persistence/LaravelDocumentStorage.php`: capability-owned immutable PDF objects.
- `backend/app/Modules/Finance/Infrastructure/Mail/*`: per-owner SMTP configuration, stable Message-ID, mail construction, and guaranteed config teardown.
- `backend/app/Modules/Finance/Infrastructure/Scheduling/*`: due-run claiming, bounded catch-up, and safe-step retry.
- `backend/app/Modules/Finance/Infrastructure/Compatibility/*`: legacy source mapping, snapshot/report projection, and cutover checks.
- `backend/app/Modules/Finance/Http/Controllers/*`: resource-specific controllers with no business logic.
- `backend/app/Modules/Finance/Http/Requests/*`: exact input validation and `Idempotency-Key` validation.
- `backend/app/Modules/Finance/Http/Resources/*`: stable minor-unit API output.
- `backend/app/Modules/Finance/Http/Routes/api.php`: preview routes first, canonical `/api/v1/finance/*` routes only at cutover.

### Frontend

- `frontend/src/modules/finance/models/money.ts`: exact decimal-string/minor-unit boundary helpers; never `parseFloat` for invoice authority.
- `frontend/src/modules/finance/models/invoice.ts`, `payment.ts`, `recurring.ts`: stable API types.
- `frontend/src/modules/finance/api/invoices.ts`, `payments.ts`, `recurring.ts`: focused API clients.
- `frontend/src/modules/finance/stores/invoices.ts`, `payments.ts`, `recurring.ts`: independent paginated stores with URL filter state.
- `frontend/src/modules/finance/components/*`: shared line editor, totals, status, activity, allocation, delivery, and retry components.
- `frontend/src/modules/finance/invoices/*`: invoice list/detail/editor/revision pages.
- `frontend/src/modules/finance/payments/*`: payment list/detail/allocation pages.
- `frontend/src/modules/finance/recurring/*`: template list/editor/run-history pages.
- `frontend/src/modules/finance/routes.ts`: lazy module routes activated by the cutover task.

---

### Task 1: Lock domain status, balance, and recurrence contracts

**Files:**
- Create: `backend/app/Modules/Finance/Domain/Invoices/InvoiceKind.php`
- Create: `backend/app/Modules/Finance/Domain/Invoices/InvoiceStatus.php`
- Create: `backend/app/Modules/Finance/Domain/Invoices/InvoiceWorkflow.php`
- Create: `backend/app/Modules/Finance/Domain/Invoices/InvoiceBalance.php`
- Create: `backend/app/Modules/Finance/Domain/Invoices/Exception/InvalidInvoiceState.php`
- Create: `backend/app/Modules/Finance/Domain/Payments/AllocationLedger.php`
- Create: `backend/app/Modules/Finance/Domain/Payments/Exception/InvalidAllocation.php`
- Create: `backend/app/Modules/Finance/Domain/Recurring/RecurrenceInterval.php`
- Create: `backend/app/Modules/Finance/Domain/Recurring/RecurrenceSchedule.php`
- Create: `backend/tests/Unit/Modules/Finance/Domain/Invoices/InvoiceWorkflowTest.php`
- Create: `backend/tests/Unit/Modules/Finance/Domain/Payments/AllocationLedgerTest.php`
- Create: `backend/tests/Unit/Modules/Finance/Domain/Recurring/RecurrenceScheduleTest.php`

**Interfaces:**
- Consumes: foundation `Money`, `DecimalQuantity`, `Rounding`, and `StateMachine`.
- Produces: `InvoiceWorkflow::assertCanFinalize|assertCanSend|assertCanCancel`, `InvoiceBalance::effectiveStatus()`, `AllocationLedger::apply()`, and `RecurrenceSchedule::nextAfter()`.

- [ ] **Step 1: Write failing invoice workflow and derived-status tests**

Pin allowed commands and precedence explicitly:

```php
$workflow->assertCanFinalize(InvoiceStatus::Draft);
$workflow->assertCanSend(InvoiceStatus::Finalized);
$workflow->assertCanCancel(InvoiceStatus::Paid);

$balance = new InvoiceBalance(grossMinor: 11900, allocatedMinor: 5000, wasSent: true, cancelled: false);
$this->assertSame(InvoiceStatus::PartiallyPaid, $balance->effectiveStatus());
$this->assertSame(6900, $balance->openMinor());

$cancelled = new InvoiceBalance(11900, 11900, true, true);
$this->assertSame(InvoiceStatus::Cancelled, $cancelled->effectiveStatus());
```

Also assert draft update after finalization, direct `sent -> paid`, self transition, cancel of a credit note, and negative open balance without overpayment allowance throw stable codes.

- [ ] **Step 2: Write failing signed-allocation tests**

```php
$ledger = AllocationLedger::forPayment(Money::fromMinor(15000, 'EUR'))
    ->apply(invoiceId: 10, amount: Money::fromMinor(11900, 'EUR'));
$this->assertSame(3100, $ledger->remaining()->minor());

$refund = AllocationLedger::forPayment(Money::fromMinor(-11900, 'EUR'))
    ->apply(invoiceId: 11, amount: Money::fromMinor(-11900, 'EUR'));
$this->assertSame(0, $refund->remaining()->minor());
```

Reject zero allocations, currency mismatch, opposite signs, allocation beyond payment magnitude, and a second reversal of the same allocation.

- [ ] **Step 3: Write failing recurrence boundary tests**

Use immutable dates and IANA timezones. Pin `2026-01-31 -> 2026-02-28 -> 2026-03-31`, leap-year `2028-02-29 -> 2029-02-28`, quarterly/half-year/year increments, explicit end date, and UTC instants across Europe/Berlin DST changes.

```php
$schedule = RecurrenceSchedule::monthly(
    new DateTimeImmutable('2026-01-31 08:00:00', new DateTimeZone('Europe/Berlin')),
);
$this->assertSame('2026-02-28T08:00:00+01:00', $schedule->nextAfter($schedule->start())->format(DATE_ATOM));
```

- [ ] **Step 4: Run tests to verify they fail**

Run:

```bash
cd backend
php artisan test tests/Unit/Modules/Finance/Domain/Invoices tests/Unit/Modules/Finance/Domain/Payments tests/Unit/Modules/Finance/Domain/Recurring
```

Expected: FAIL because the domain classes do not exist.

- [ ] **Step 5: Implement immutable integer-only domain objects**

Use backed enums for public state names, the foundation state machine for workflow guards, and checked integer addition/subtraction for ledger totals. `RecurrenceSchedule` keeps `anchorDay` and `monthEndAnchor`; it converts only at the timezone boundary and never computes intervals as a fixed number of seconds.

```php
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
```

- [ ] **Step 6: Run focused quality gates and commit**

```bash
cd backend
php artisan test tests/Unit/Modules/Finance/Domain/Invoices tests/Unit/Modules/Finance/Domain/Payments tests/Unit/Modules/Finance/Domain/Recurring
vendor/bin/pint --test app/Modules/Finance/Domain tests/Unit/Modules/Finance/Domain
git add app/Modules/Finance/Domain tests/Unit/Modules/Finance/Domain
git commit -m "feat(finance): define invoice payment and recurrence rules"
```

### Task 2: Add invoice, numbering, delivery, and idempotency schema

**Files:**
- Create: `backend/database/migrations/2026_08_28_110000_create_finance_invoices.php`
- Create: `backend/tests/Feature/FinanceModule/InvoiceSchemaTest.php`

**Interfaces:**
- Consumes: `finance_document_series`, `finance_document_revisions`, users, legacy partner/project/product identifiers only as nullable compatibility references.
- Produces: `finance_invoices`, `finance_invoice_sequences`, `finance_invoice_deliveries`, and `finance_idempotency_records`.

- [ ] **Step 1: Write failing schema and cross-owner integrity tests**

Assert these exact constraints:

```text
finance_invoices:
  unique (user_id, document_series_id)
  unique (user_id, uuid)
  unique (user_id, source_type, source_key) where source_type/source_key are not null
  unique (user_id, year, number) where number is not null
  unique cancellation target where cancels_invoice_id is not null
  composite owner/series and owner/series/revision foreign keys
finance_invoice_sequences:
  unique (user_id, series_key, year)
finance_invoice_deliveries:
  unique (user_id, kind, idempotency_key_hash)
finance_idempotency_records:
  unique (user_id, operation, key_hash)
```

Insert cross-owner series/revision/cancellation references with raw query builder calls and require database rejection on PostgreSQL and SQLite.

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/FinanceModule/InvoiceSchemaTest.php`

Expected: FAIL because the tables do not exist.

- [ ] **Step 3: Create additive invoice schema**

`finance_invoices` stores internal/public identity, series/current revision, `kind`, number/year/sequence, issue/due dates, nullable partner/project compatibility references, source identity and source snapshot hash, workflow timestamps, signed `allocated_minor`, signed `open_minor`, `version`, and cancellation relation. Keep customer, lines, totals, texts, and PDF metadata out of this table; they belong to the revision snapshot/core columns. Resources expose `paid_minor` as the non-negative magnitude applied to a normal invoice and keep the signed ledger value explicit for credit documents.

`finance_invoice_deliveries` stores invoice/revision, `invoice|reminder`, recipient, stable message ID, `pending|sending|sent|failed|unknown`, attempts, redacted error code, timestamps, and the hashed idempotency key. It never stores SMTP credentials or message bodies.

- [ ] **Step 4: Add database checks and indexes**

Enforce valid kinds, non-negative sequence, `(number, year, sequence)` all-null-or-all-present, three-letter currency through the revision relation, sign-compatible `allocated_minor`/`open_minor` at the Application layer, and indexes for owner/status/date, owner/due/open, delivery status/retry time, and sequence lookup. Use PostgreSQL checks and equivalent SQLite test triggers as in the foundation migration.

- [ ] **Step 5: Run migration cycle and commit**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/InvoiceSchemaTest.php
php artisan migrate:fresh --env=testing --force
git add database/migrations/2026_08_28_110000_create_finance_invoices.php tests/Feature/FinanceModule/InvoiceSchemaTest.php
git commit -m "feat(finance): add invoice workflow schema"
```

### Task 3: Add signed payment and append-only allocation schema

**Files:**
- Create: `backend/database/migrations/2026_08_28_110100_create_finance_payments.php`
- Create: `backend/tests/Feature/FinanceModule/PaymentSchemaTest.php`

**Interfaces:**
- Consumes: `finance_invoices`, users, and optional legacy `payment_methods`/`bank_transactions` source IDs.
- Produces: `finance_payments`, `finance_payment_allocation_batches`, and `finance_payment_allocations`.

- [ ] **Step 1: Write failing owner, uniqueness, and append-only tests**

Require unique `(user_id, uuid)`, unique `(user_id, source_type, source_key)`, unique allocation-batch idempotency keys, composite owner foreign keys from allocation to payment/invoice, and unique `reverses_allocation_id` so an allocation can be reversed once.

```php
$this->expectException(QueryException::class);
DB::table('finance_payment_allocations')->insert([
    'user_id' => $ownerA->id,
    'payment_id' => $paymentA,
    'invoice_id' => $invoiceB,
    'amount_minor' => 100,
]);
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/FinanceModule/PaymentSchemaTest.php`

- [ ] **Step 3: Implement signed-minor-unit tables**

Payments contain non-zero signed `amount_minor`, currency, received date/time, reference, counterparty, optional account/source identity, and version. Allocation batches contain the payment, request hash, idempotency hash, and actor. Allocation rows contain batch/payment/invoice, signed amount, optional reversed row, and timestamp; they have no update/delete timestamps because corrections append inverse rows.

- [ ] **Step 4: Add immutable record-level expectations to the schema test**

Assert raw DB deletion is restricted while references exist and owner deletion cascades. Record API mutation guards are implemented in Task 5; this task pins database ownership and referential behavior only.

- [ ] **Step 5: Run and commit**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/PaymentSchemaTest.php
php artisan migrate:fresh --env=testing --force
git add database/migrations/2026_08_28_110100_create_finance_payments.php tests/Feature/FinanceModule/PaymentSchemaTest.php
git commit -m "feat(finance): add payment allocation schema"
```

### Task 4: Add versioned recurring template and run schema

**Files:**
- Create: `backend/database/migrations/2026_08_28_110200_create_finance_recurring_invoices.php`
- Create: `backend/tests/Feature/FinanceModule/RecurringInvoiceSchemaTest.php`

**Interfaces:**
- Consumes: users and generated `finance_invoices`.
- Produces: `finance_recurring_invoice_templates`, `finance_recurring_invoice_template_versions`, and `finance_recurring_invoice_runs`.

- [ ] **Step 1: Write failing version/run integrity tests**

Assert positive, unique template version numbers; unique `(template_id, scheduled_for)` runs; owner-matched template/version/invoice references; and that a run's version belongs to its template.

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/FinanceModule/RecurringInvoiceSchemaTest.php`

- [ ] **Step 3: Implement template and immutable version schema**

Templates store UUID, owner, mode, interval, IANA timezone, start/end local dates, local run time, anchor day/month-end flag, `next_run_at` UTC, paused timestamp, current version, and optimistic version. Template versions store positive number, `effective_from` local date, canonical invoice-draft snapshot, snapshot SHA-256, actor, and creation time; update/delete is forbidden after insert.

- [ ] **Step 4: Implement resumable run schema**

Runs store scheduled UTC instant and local date, template version, `pending|creating_draft|draft_created|finalizing|finalized|sending|sent|failed`, safe last completed step, invoice, delivery, attempts, next retry time, redacted error code/detail, and timestamps. Add owner/status/retry and template/schedule indexes.

- [ ] **Step 5: Run and commit**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/RecurringInvoiceSchemaTest.php
php artisan migrate:fresh --env=testing --force
git add database/migrations/2026_08_28_110200_create_finance_recurring_invoices.php tests/Feature/FinanceModule/RecurringInvoiceSchemaTest.php
git commit -m "feat(finance): add recurring invoice schema"
```

### Task 5: Implement owner-scoped records, repositories, and immutable ledgers

**Files:**
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/InvoiceRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/InvoiceSequenceRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/InvoiceDeliveryRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/IdempotencyRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/PaymentRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/PaymentAllocationBatchRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/PaymentAllocationRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/RecurringInvoiceTemplateRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/RecurringInvoiceTemplateVersionRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/RecurringInvoiceRunRecord.php`
- Create: `backend/app/Modules/Finance/Application/Ports/InvoiceRepository.php`
- Create: `backend/app/Modules/Finance/Application/Ports/PaymentRepository.php`
- Create: `backend/app/Modules/Finance/Application/Ports/RecurringInvoiceRepository.php`
- Create: `backend/app/Modules/Finance/Application/Ports/IdempotencyStore.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Clock.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/IdempotencyKey.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Invoices/InvoiceId.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Invoices/InvoiceLineData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Invoices/InvoiceDraftData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Invoices/InvoiceView.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Invoices/FinalizedInvoice.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Invoices/DeliveryId.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Payments/PaymentId.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Payments/RecordPaymentData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Payments/AllocatePaymentData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Payments/AllocationLineData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Payments/PaymentView.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Payments/AllocationId.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Payments/AllocationResult.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Recurring/RecurringTemplateId.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Recurring/RecurringRunId.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentInvoiceRepository.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentPaymentRepository.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentRecurringInvoiceRepository.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentIdempotencyStore.php`
- Create: `backend/app/Modules/Finance/Infrastructure/SystemClock.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/tests/Feature/FinanceModule/FinanceInvoicePersistenceTest.php`

**Interfaces:**
- Consumes: Tasks 2–4 and foundation owner scope/guarded mutation patterns.
- Produces: validated core IDs/data/results, an injectable clock, transaction-safe repository ports, and immutable allocation/template-version records. Later command tasks modify behavior around these DTOs; they do not rename or duplicate them.

- [ ] **Step 1: Write failing authenticated-owner and foreign-ID tests**

For each repository, authenticate owner A, create owner A and B data, and prove UUID, numeric ID, nested revision, payment, allocation, template, and run lookup cannot cross owners. Repeat one case with a Sanctum token to exercise request-scope owner binding.

- [ ] **Step 2: Write failing mutation-guard tests**

Assert allocation entries and template versions reject `save`, `update`, `delete`, quiet writes, and guarded builder updates. Delivery rows may advance only through the repository's compare-and-set methods; invoice numbering/source/cancellation fields stay non-fillable.

- [ ] **Step 3: Define repository contracts with exact signatures**

```php
interface InvoiceRepository
{
    public function createDraft(InvoiceDraftData $data): InvoiceId;
    public function updateDraft(InvoiceId $id, InvoiceDraftData $data, int $expectedVersion): InvoiceView;
    public function finalize(InvoiceId $id, IdempotencyKey $key, Closure $publish): FinalizedInvoice;
    public function markDeliverySent(DeliveryId $deliveryId, DateTimeImmutable $at): InvoiceView;
}

interface PaymentRepository
{
    public function record(RecordPaymentData $data, IdempotencyKey $key): PaymentView;
    public function allocate(AllocatePaymentData $data, IdempotencyKey $key): AllocationResult;
    public function reverse(AllocationId $id, IdempotencyKey $key): AllocationResult;
}
```

Implement the listed DTOs as readonly validated values in this step so the repositories, feature tests, and PHPStan gate compile independently. IDs are positive integers; idempotency keys are trimmed 1–128 byte opaque values whose raw value never reaches persistence/logs; amount fields are integers; currency is uppercase `[A-Z]{3}`; line quantity remains the canonical scale-4 string.

- [ ] **Step 4: Implement locks and compare-and-set writes**

Use lock order `series -> invoice -> revision -> payment -> allocations -> delivery/run`. Normalize unique-constraint failures to stable exceptions; retry only known sequence/idempotency races. Recompute signed `allocated_minor`/`open_minor` from allocation sums under the invoice lock after every allocation or reversal.

- [ ] **Step 5: Register bindings and verify**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/FinanceInvoicePersistenceTest.php
vendor/bin/pint --test app/Modules/Finance/Infrastructure/Persistence app/Modules/Finance/Application/Ports tests/Feature/FinanceModule/FinanceInvoicePersistenceTest.php
vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G
git add app/Modules/Finance tests/Feature/FinanceModule/FinanceInvoicePersistenceTest.php
git commit -m "feat(finance): persist invoice payment and recurring aggregates"
```

### Task 6: Build exact invoice drafts and the single external source contract

**Files:**
- Modify: `backend/app/Modules/Finance/Application/DTOs/Invoices/InvoiceLineData.php`
- Modify: `backend/app/Modules/Finance/Application/DTOs/Invoices/InvoiceDraftData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Invoices/InvoiceDraftSource.php`
- Modify: `backend/app/Modules/Finance/Application/DTOs/Invoices/InvoiceView.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Invoices/CreateInvoiceDraft.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Invoices/UpdateInvoiceDraft.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Invoices/CreateInvoiceDraftFromSource.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Invoices/DeleteInvoiceDraft.php`
- Create: `backend/tests/Feature/FinanceModule/InvoiceDraftApplicationTest.php`
- Create: `backend/tests/Feature/FinanceModule/InvoiceSourceContractTest.php`

**Interfaces:**
- Consumes: foundation calculator/revision services and `InvoiceRepository`.
- Produces: one manual draft path and one idempotent source path usable by quotes, projects, imports, and recurring runs.

- [ ] **Step 1: Write failing exact-input and server-authority tests**

```php
$data = new InvoiceDraftData(
    issueDate: new DateTimeImmutable('2026-08-28'),
    dueDate: new DateTimeImmutable('2026-09-11'),
    currency: 'EUR',
    customer: ['name' => 'ACME'],
    lines: [new InvoiceLineData('Work', '2.5000', 10000, 1900, 'h', null, null)],
    discount: Discount::none('EUR'),
    controlNetMinor: 25000,
    controlVatMinor: 4750,
    controlGrossMinor: 29750,
);
```

Assert canonical quantity strings/minor units in the stored snapshot, exact control mismatch rejection, float rejection anywhere in the input snapshot, optimistic version conflict, draft-only deletion, and no client workflow/number/PDF fields.

- [ ] **Step 2: Write failing source idempotency tests**

Construct `InvoiceDraftSource` with `sourceType='quote_revision'`, stable source key, source revision ID, and SHA-256. Two calls return the same invoice; a second payload under the same source identity returns `source_snapshot_conflict`; another owner's source/partner/project ID returns 404.

- [ ] **Step 3: Verify failure**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/InvoiceDraftApplicationTest.php tests/Feature/FinanceModule/InvoiceSourceContractTest.php
```

- [ ] **Step 4: Implement canonical draft snapshot creation/update**

Create a series with `document_type=invoice`, create or update its single unpublished revision, replace caller `lines`/`totals` with authoritative values, and append `invoice.draft.created`/`invoice.draft.updated`. A source snapshot is copied as data and hash metadata; the invoice module does not ask whether a quote was accepted or expired.

- [ ] **Step 5: Freeze the cross-module source signature**

```php
final readonly class InvoiceDraftSource
{
    public function __construct(
        public string $sourceType,
        public string $sourceKey,
        public int $sourceRevisionId,
        public string $sourceSnapshotSha256,
        public InvoiceDraftData $draft,
    ) {}
}

final readonly class CreateInvoiceDraftFromSource
{
    public function handle(InvoiceDraftSource $source, IdempotencyKey $key): InvoiceView;
}
```

Document allowed source labels (`quote_revision`, `legacy_quote_snapshot`, `project_time_batch`, `recurring_run`, `cancellation`, `legacy_invoice`) as identifiers only, not behavior switches.

- [ ] **Step 6: Run and commit**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/InvoiceDraftApplicationTest.php tests/Feature/FinanceModule/InvoiceSourceContractTest.php
vendor/bin/pint --test app/Modules/Finance/Application tests/Feature/FinanceModule
git add app/Modules/Finance/Application tests/Feature/FinanceModule/InvoiceDraftApplicationTest.php tests/Feature/FinanceModule/InvoiceSourceContractTest.php
git commit -m "feat(finance): add exact invoice draft commands"
```

### Task 7: Implement atomic finalization, numbering, and inventory effects

**Files:**
- Modify: `backend/app/Modules/Finance/Application/DTOs/Invoices/FinalizedInvoice.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Invoices/FinalizeInvoice.php`
- Create: `backend/app/Modules/Finance/Application/Ports/InvoiceNumberAllocator.php`
- Create: `backend/app/Modules/Finance/Application/Ports/InventoryMovementPort.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/LockedInvoiceNumberAllocator.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Inventory/LegacyStockLedgerAdapter.php`
- Create: `backend/database/migrations/2026_08_28_110050_harden_invoice_stock_idempotency.php`
- Create: `backend/tests/Feature/FinanceModule/InvoiceFinalizationTest.php`

**Interfaces:**
- Consumes: draft revision, `DocumentRenderer`, `DocumentStorage`, number settings, and inventory port.
- Produces: idempotent `FinalizeInvoice::handle(InvoiceId, IdempotencyKey): FinalizedInvoice`.

- [ ] **Step 1: Write failing finalization transaction tests**

Assert one call allocates number, recalculates totals, injects number/dates/company snapshot, publishes revision/PDF, records `invoice.finalized`, and writes one aggregated stock movement per hardware product. Assert retry returns identical invoice/revision/path/hash and creates no second activity or stock row.

- [ ] **Step 2: Pin all rollback cases**

Use fakes that throw during calculation validation, sequence allocation, PDF render, storage, and inventory write. After each failure assert draft status, null number/publication, unchanged sequence counter, no stock movement, no success activity, and no reachable PDF. A storage object orphaned by a database commit failure must be found by the storage reconciler test and safely removed.

- [ ] **Step 3: Pin concurrent first-number allocation**

Run two database connections against the same owner/year and assert distinct contiguous numbers. The allocator first inserts the unique `(owner, series_key='invoice', year)` row if absent, then locks and increments it; uniqueness retry handles the initial insert race.

- [ ] **Step 4: Implement finalization in one outer transaction**

Order operations: lock series/invoice/revision; revalidate draft and source; recalculate; allocate number; rewrite the unpublished canonical snapshot with authoritative number/totals/company data; aggregate required inventory movements; publish the revision; write movements with unique `finance_invoice:{invoice_uuid}` references; set series workflow to finalized and invoice timestamps/version; append activity. Do not catch and downgrade inventory errors.

- [ ] **Step 5: Make legacy inventory idempotent and exact**

Expand legacy product/movement quantity storage to four decimals in the additive migration, aggregate duplicate product lines, and add a unique partial index over owner/product/ref type/ref ID for invoice sale movements. The adapter validates the product belongs to the owner and is hardware before writing.

- [ ] **Step 6: Run and commit**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/InvoiceFinalizationTest.php tests/Feature/FinanceProductTest.php
vendor/bin/pint --test app/Modules/Finance tests/Feature/FinanceModule/InvoiceFinalizationTest.php
git add app/Modules/Finance database/migrations/2026_08_28_110050_harden_invoice_stock_idempotency.php tests/Feature/FinanceModule/InvoiceFinalizationTest.php
git commit -m "feat(finance): finalize invoices atomically"
```

### Task 8: Supply deterministic PDF rendering, immutable storage, and secure streaming

**Files:**
- Modify: `backend/composer.json`
- Modify: `backend/composer.lock`
- Create: `backend/resources/views/finance/invoices/pdf.blade.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Pdf/BladeDocumentRenderer.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/LaravelDocumentStorage.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/OrphanDocumentReconciler.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/InvoiceRevisionController.php`
- Create: `backend/app/Modules/Finance/Http/Resources/InvoiceRevisionResource.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/tests/Feature/FinanceModule/InvoicePdfTest.php`

**Interfaces:**
- Consumes: canonical immutable revision snapshots and foundation renderer/storage ports.
- Produces: production `DocumentRenderer`, `DocumentStorage`, revision metadata, and authorized PDF streaming.

- [ ] **Step 1: Add the PDF dependency through Composer resolution**

Run `cd backend && composer require dompdf/dompdf`. Commit the resolved root constraint and exact lockfile; do not rely on the optional transitive FPDF package.

- [ ] **Step 2: Write failing deterministic renderer tests**

Render a mixed-rate/discount invoice twice and assert `%PDF-`, identical SHA-256, expected number/customer/minor-unit totals via `pdftotext`, no external URL fetch, and escaped customer HTML. Freeze locale, timezone, and timestamps in the snapshot so render output has no ambient clock dependency.

- [ ] **Step 3: Write failing storage and streaming tests**

Assert each ownership token creates one path derived from `sha256(token)` below `finance/documents/`, never replaces an existing path, verifies MIME/header and SHA-256, deletes only its own object, and streams only through an owner-scoped revision with `application/pdf`, `nosniff`, sandbox CSP, private cache, and safe filename. Foreign owner and guessed path return 404.

- [ ] **Step 4: Implement hardened rendering/storage**

Disable remote fetching and PHP execution in Dompdf, chroot assets to repository-owned invoice fonts/images, escape every user field, and render only an allowlisted template selected by server snapshot. `LaravelDocumentStorage` computes a deterministic token-owned path without exposing the raw capability and writes with exclusive-create semantics; `OrphanDocumentReconciler` deletes only unreferenced Finance document paths older than a grace period.

- [ ] **Step 5: Bind adapters and run tests**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/InvoicePdfTest.php tests/Feature/FinanceModule/DocumentRevisionApplicationTest.php
vendor/bin/pint --test app/Modules/Finance/Infrastructure/Pdf app/Modules/Finance/Infrastructure/Persistence app/Modules/Finance/Http tests/Feature/FinanceModule/InvoicePdfTest.php
vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G
git add composer.json composer.lock resources/views/finance/invoices app/Modules/Finance tests/Feature/FinanceModule/InvoicePdfTest.php
git commit -m "feat(finance): render immutable invoice PDFs"
```

### Task 9: Implement idempotent invoice delivery and dunning

**Files:**
- Modify: `backend/app/Modules/Finance/Application/DTOs/Invoices/DeliveryId.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Invoices/QueueInvoiceDelivery.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Invoices/RetryInvoiceDelivery.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Invoices/QueueInvoiceReminder.php`
- Create: `backend/app/Modules/Finance/Application/Queries/InvoiceAgingQuery.php`
- Create: `backend/app/Modules/Finance/Application/Ports/InvoiceMailer.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Mail/CompanyInvoiceMailer.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Mail/InvoiceRevisionMail.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Scheduling/SendInvoiceDeliveryJob.php`
- Create: `backend/tests/Feature/FinanceModule/InvoiceDeliveryTest.php`
- Create: `backend/tests/Feature/FinanceModule/InvoiceDunningTest.php`

**Interfaces:**
- Consumes: finalized revision/PDF, owner company SMTP settings, delivery repository, queue.
- Produces: 202-style delivery resources, retryable failure states, successful `finalized -> sent`, and derived overdue aging.

- [ ] **Step 1: Write failing delivery state/idempotency tests**

Assert a finalized invoice queues one delivery after commit; duplicate key/payload returns it; different payload conflicts; draft/cancelled invoice rejects; missing recipient/PDF/SMTP produces stable validation errors before dispatch. A successful job stamps delivery sent, transitions workflow to sent once, and appends `invoice.sent` without exposing the full recipient in activity/log output.

- [ ] **Step 2: Write failing failure and unknown-state tests**

Assert SMTP rejection records `failed` plus redacted code and leaves invoice finalized. A retry uses the same immutable revision/PDF and a new bounded attempt. Simulate transport success followed by persistence failure and require `unknown`; automatic retry stops there until an explicit user retry to prevent claiming exactly-once semantics.

- [ ] **Step 3: Write failing overdue/dunning tests**

Use the owner's timezone from `UserSetting::timezone`, falling back to application timezone. Assert sent unpaid and partially paid remainders are bucketed; finalized-unsent, paid, cancelled, future-due, zero/negative-open documents are excluded. Reminder delivery requires overdue state and records one append-only activity per successful level.

- [ ] **Step 4: Implement Octane-safe mail adapter**

Build runtime SMTP config per owner, apply `OutboundUrl::hostAllowed`, use a stable Message-ID derived from delivery UUID, attach bytes by immutable revision path/hash, and tear config down in `finally` on success and exception. Limit the queued job to 3 attempts with backoff `[60, 300, 1800]` seconds.

- [ ] **Step 5: Run and commit**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/InvoiceDeliveryTest.php tests/Feature/FinanceModule/InvoiceDunningTest.php tests/Feature/InvoiceEmailTest.php tests/Feature/InvoiceDunTest.php tests/Feature/InvoiceReminderTest.php
vendor/bin/pint --test app/Modules/Finance tests/Feature/FinanceModule
git add app/Modules/Finance tests/Feature/FinanceModule
git commit -m "feat(finance): add invoice delivery and dunning"
```

### Task 10: Implement payments, partial allocations, overpayments, and suggestions

**Files:**
- Modify: `backend/app/Modules/Finance/Application/DTOs/Payments/RecordPaymentData.php`
- Modify: `backend/app/Modules/Finance/Application/DTOs/Payments/AllocatePaymentData.php`
- Modify: `backend/app/Modules/Finance/Application/DTOs/Payments/AllocationLineData.php`
- Modify: `backend/app/Modules/Finance/Application/DTOs/Payments/PaymentView.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Payments/RecordPayment.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Payments/AllocatePayment.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Payments/ReversePaymentAllocation.php`
- Create: `backend/app/Modules/Finance/Application/Queries/SuggestPaymentAllocations.php`
- Create: `backend/tests/Feature/FinanceModule/PaymentApplicationTest.php`
- Create: `backend/tests/Unit/Modules/Finance/Application/PaymentSuggestionTest.php`

**Interfaces:**
- Consumes: payment repository, signed allocation ledger, finalized invoices.
- Produces: manual/import-compatible payment recording, idempotent allocation/reversal, and non-mutating suggestions.

- [ ] **Step 1: Write failing manual/import payment tests**

Assert exact positive/negative minor amounts, ISO currency, owner-scoped account/source IDs, source-key idempotency, optimistic version, and rejection of zero or float amounts. Imported and manual paths must both call `RecordPayment`; only their source metadata differs.

- [ ] **Step 2: Write failing allocation tests**

Pin these cases with exact integers: 5,000 of 11,900 produces `partially_paid` and 6,900 open; a second 6,900 produces `paid`; one 25,000 payment allocates 11,900 and 8,100 across two invoices; 15,000 against 11,900 leaves 3,100 unapplied; a -11,900 refund settles a -11,900 credit note. Reject cross-owner, cross-currency, draft, cancelled target, wrong sign, over-allocation, and duplicate reversal.

- [ ] **Step 3: Write failing suggestion tests**

Score exact normalized invoice number/reference first, exact currency/remaining amount second, and amount/date/customer evidence after that. Return `suggested` only when the best candidate is unique; return an ordered ambiguous list without mutation for equal candidates. Never auto-confirm.

```php
$result = $query->forPayment($paymentId);
$this->assertSame('unique_reference_and_amount', $result->candidates[0]->reason);
$this->assertTrue($result->requiresConfirmation);
$this->assertDatabaseCount('finance_payment_allocations', 0);
```

- [ ] **Step 4: Implement append-only application commands**

Hash idempotency keys, hash the canonical request, lock payment and all target invoices in ascending ID order, append batch/entries, recompute each invoice projection, append payment activities, and return current payment plus affected invoices. Reversal appends the exact negation linked to the original entry.

- [ ] **Step 5: Run and commit**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/PaymentApplicationTest.php tests/Unit/Modules/Finance/Application/PaymentSuggestionTest.php
vendor/bin/pint --test app/Modules/Finance/Application tests/Feature/FinanceModule/PaymentApplicationTest.php tests/Unit/Modules/Finance/Application/PaymentSuggestionTest.php
git add app/Modules/Finance/Application tests/Feature/FinanceModule/PaymentApplicationTest.php tests/Unit/Modules/Finance/Application/PaymentSuggestionTest.php
git commit -m "feat(finance): allocate exact invoice payments"
```

### Task 11: Implement cancellation as a separately numbered immutable document

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Invoices/CancelInvoiceData.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Invoices/CancelInvoice.php`
- Create: `backend/tests/Feature/FinanceModule/InvoiceCancellationTest.php`

**Interfaces:**
- Consumes: finalized invoice revision, finalization pipeline, numbering, inventory port, and idempotency store.
- Produces: a credit/cancellation invoice with its own series, revision, PDF, number, and relation to the unchanged original.

- [ ] **Step 1: Write failing exact reversal tests**

Create a discounted mixed-tax invoice, allocate a partial payment, snapshot every original DB field/hash, then cancel. Assert the new document has negative authoritative lines/totals, a distinct number/revision/PDF hash, `cancels_invoice_id`, reverse inventory movements, and activities on both series; byte-for-byte original row/revision/PDF and allocations remain unchanged.

- [ ] **Step 2: Write failing state/idempotency/concurrency tests**

Reject draft and credit-note cancellation. Two concurrent keys for one original produce one cancellation via the unique relation; retries return it. Paid invoices may be cancelled but expose an outstanding refund through the negative credit document; no refund payment is fabricated.

- [ ] **Step 3: Implement through the normal draft/finalization path**

Build `InvoiceDraftSource(sourceType='cancellation', sourceKey=original UUID, sourceRevisionId, source hash)` from the immutable revision, negate quantity or unit-price consistently without floats, copy customer/tax/discount texts, create the new draft, link it to the original under lock, and invoke `FinalizeInvoice`. Do not special-case totals by copying negative aggregate columns.

- [ ] **Step 4: Run and commit**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/InvoiceCancellationTest.php tests/Feature/InvoiceStornoTest.php tests/Feature/InvoiceDiscountTest.php
vendor/bin/pint --test app/Modules/Finance/Application/Commands/Invoices tests/Feature/FinanceModule/InvoiceCancellationTest.php
git add app/Modules/Finance/Application/Commands/Invoices app/Modules/Finance/Application/DTOs/Invoices tests/Feature/FinanceModule/InvoiceCancellationTest.php
git commit -m "feat(finance): cancel invoices with credit documents"
```

### Task 12: Implement effective-dated recurring template commands

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Recurring/RecurringTemplateData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Recurring/RecurringTemplateVersionData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Recurring/RecurringTemplateView.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Recurring/CreateRecurringInvoiceTemplate.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Recurring/AddRecurringInvoiceTemplateVersion.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Recurring/PauseRecurringInvoiceTemplate.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Recurring/ResumeRecurringInvoiceTemplate.php`
- Create: `backend/tests/Feature/FinanceModule/RecurringTemplateApplicationTest.php`

**Interfaces:**
- Consumes: recurrence domain and exact `InvoiceDraftData` snapshot rules.
- Produces: versioned templates with deterministic next-run calculation.

- [ ] **Step 1: Write failing create/version tests**

Assert the initial version is 1, canonical/hash stable, next run is UTC for the local start/time, partner/source IDs owner-scoped, and invalid timezone/end-before-start/unsupported interval reject. A new version has an explicit effective date and cannot mutate existing versions or runs.

- [ ] **Step 2: Write failing pause/resume tests**

Pause preserves generated invoices/runs and the next due occurrence. Resume recalculates only whether it is eligible for claiming; it does not skip overdue occurrences. Optimistic version conflicts return the current template.

- [ ] **Step 3: Implement version selection**

For an occurrence local date, select the highest `(effective_from, version_number)` not after that date. Existing runs retain their referenced version even if a later effective-dated version is inserted.

- [ ] **Step 4: Run and commit**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/RecurringTemplateApplicationTest.php tests/Unit/Modules/Finance/Domain/Recurring/RecurrenceScheduleTest.php
vendor/bin/pint --test app/Modules/Finance/Application/Commands/Recurring app/Modules/Finance/Application/DTOs/Recurring tests/Feature/FinanceModule/RecurringTemplateApplicationTest.php
git add app/Modules/Finance/Application/Commands/Recurring app/Modules/Finance/Application/DTOs/Recurring tests/Feature/FinanceModule/RecurringTemplateApplicationTest.php
git commit -m "feat(finance): version recurring invoice templates"
```

### Task 13: Implement idempotent scheduler catch-up and safe-step retry

**Files:**
- Create: `backend/app/Modules/Finance/Application/Commands/Recurring/ClaimDueRecurringInvoiceRuns.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Recurring/ProcessRecurringInvoiceRun.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Recurring/RetryRecurringInvoiceRun.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Scheduling/RunRecurringInvoices.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Scheduling/ProcessRecurringInvoiceRunJob.php`
- Modify: `backend/routes/console.php`
- Create: `backend/tests/Feature/FinanceModule/RecurringInvoiceSchedulerTest.php`

**Interfaces:**
- Consumes: template repository, `CreateInvoiceDraftFromSource`, finalization, delivery, queue, and clock.
- Produces: `finance:run-recurring-invoices`, unique due runs, bounded catch-up, and resumable processing.

- [ ] **Step 1: Write failing scheduler concurrency/catch-up tests**

Freeze time, run two claimers, and assert one row per `(template, scheduled_for)`. Seed 250 missed monthly occurrences; one tick claims at most 100 per template and advances `next_run_at` only through claimed occurrences, while three ticks eventually claim all without gaps. End date and paused templates create none.

- [ ] **Step 2: Write failing mode and safe-resume tests**

For `draft`, assert one draft and `draft_created`. For `auto_send`, assert draft -> finalized/PDF -> delivery pending -> sent. Inject failures at draft, PDF, and mail: retry creates no second invoice, never re-finalizes an already finalized revision, and retries only the delivery after mail failure.

- [ ] **Step 3: Implement claiming transaction**

Select due templates ordered by next run, lock rows with `skipLocked` where supported, calculate occurrences in the template timezone, insert runs with unique protection, select version by local date, advance next run, commit, then dispatch jobs after commit. Cap global claims at 1,000 and per-template claims at 100 per tick.

- [ ] **Step 4: Implement run state machine and job policy**

`ProcessRecurringInvoiceRun` locks the run, inspects persisted invoice/revision/delivery state, executes only the next incomplete step, records attempts and safe error code, and uses source key `recurring-run:{run UUID}`. Job uses `ShouldBeUnique` by run UUID, 5 attempts, and backoff `[60, 300, 1800, 7200]`.

- [ ] **Step 5: Wire the scheduler and commit**

```php
Schedule::command('finance:run-recurring-invoices')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
```

```bash
cd backend
php artisan test tests/Feature/FinanceModule/RecurringInvoiceSchedulerTest.php
vendor/bin/pint --test app/Modules/Finance/Infrastructure/Scheduling app/Modules/Finance/Application/Commands/Recurring routes/console.php tests/Feature/FinanceModule/RecurringInvoiceSchedulerTest.php
git add app/Modules/Finance routes/console.php tests/Feature/FinanceModule/RecurringInvoiceSchedulerTest.php
git commit -m "feat(finance): run recurring invoices idempotently"
```

### Task 14: Expose paginated preview API and update OpenAPI

**Files:**
- Create: `backend/app/Modules/Finance/Http/Controllers/InvoiceController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/InvoiceWorkflowController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/InvoiceDeliveryController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/PaymentController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/PaymentAllocationController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/PaymentSuggestionController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/RecurringInvoiceTemplateController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/RecurringInvoiceRunController.php`
- Create: `backend/app/Modules/Finance/Http/Requests/Invoices/*`
- Create: `backend/app/Modules/Finance/Http/Requests/Payments/*`
- Create: `backend/app/Modules/Finance/Http/Requests/Recurring/*`
- Create: `backend/app/Modules/Finance/Http/Resources/InvoiceResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/InvoiceDeliveryResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/PaymentResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/RecurringInvoiceTemplateResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/RecurringInvoiceRunResource.php`
- Modify: `backend/app/Modules/Finance/Http/Routes/api.php`
- Modify: `openapi.yaml`
- Create: `backend/tests/Feature/FinanceModule/InvoiceApiTest.php`
- Create: `backend/tests/Feature/FinanceModule/PaymentApiTest.php`
- Create: `backend/tests/Feature/FinanceModule/RecurringInvoiceApiTest.php`

**Interfaces:**
- Consumes: Tasks 6–13.
- Produces: complete preview API below `/api/v1/finance-v2`, ready to move to canonical paths at cutover.

- [ ] **Step 1: Write failing API contract/security tests**

For every route assert unauthenticated rejection, module gate, owner 404, validation 422, throttle presence, success response, and returned `version`. Lists assert `data`, `links`, `meta`, `per_page <= 100`, stable sort, and URL filters. Mutations assert workflow/status fields in generic bodies are ignored or rejected.

- [ ] **Step 2: Pin the route surface**

```text
GET/POST   /api/v1/finance-v2/invoices
GET/PATCH  /api/v1/finance-v2/invoices/{invoice}
DELETE     /api/v1/finance-v2/invoices/{invoice}                 draft only
POST       /api/v1/finance-v2/invoices/{invoice}/finalize
POST       /api/v1/finance-v2/invoices/{invoice}/deliveries
POST       /api/v1/finance-v2/invoices/{invoice}/reminders
POST       /api/v1/finance-v2/invoices/{invoice}/cancel
GET        /api/v1/finance-v2/invoices/{invoice}/revisions
GET        /api/v1/finance-v2/invoices/{invoice}/revisions/{revision}/pdf
GET/POST   /api/v1/finance-v2/payments
GET        /api/v1/finance-v2/payments/{payment}
GET        /api/v1/finance-v2/payments/{payment}/suggestions
POST       /api/v1/finance-v2/payments/{payment}/allocations
POST       /api/v1/finance-v2/payment-allocations/{allocation}/reverse
GET/POST   /api/v1/finance-v2/recurring-invoice-templates
GET/PATCH  /api/v1/finance-v2/recurring-invoice-templates/{template}
POST       /api/v1/finance-v2/recurring-invoice-templates/{template}/versions
POST       /api/v1/finance-v2/recurring-invoice-templates/{template}/pause
POST       /api/v1/finance-v2/recurring-invoice-templates/{template}/resume
GET        /api/v1/finance-v2/recurring-invoice-templates/{template}/runs
POST       /api/v1/finance-v2/recurring-invoice-runs/{run}/retry
```

- [ ] **Step 3: Implement thin HTTP adapters**

Requests parse decimal strings into `DecimalQuantity`, integers into `Money::fromMinor`, validate IANA timezone/date/filter/header syntax, and build DTOs. Controllers call exactly one command/query and map stable domain codes to 404/409/422/202. Resources expose integer minor units, canonical quantity strings, effective status, overdue/open/paid values, immutable revision URLs, and async delivery/run states; they never expose storage paths, idempotency hashes, SMTP settings, or raw exception messages.

- [ ] **Step 4: Document every preview contract in OpenAPI**

Define reusable `MoneyMinor`, `InvoiceLineInput`, `Invoice`, `Payment`, `Allocation`, `RecurringInvoiceTemplate`, `RecurringInvoiceRun`, pagination, idempotency header, and stable error schemas. Mark preview paths `x-ledgerline-cutover-target` with their canonical future `/finance/*` path and keep the legacy contracts intact until Task 17.

- [ ] **Step 5: Run API guards and commit**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/InvoiceApiTest.php tests/Feature/FinanceModule/PaymentApiTest.php tests/Feature/FinanceModule/RecurringInvoiceApiTest.php tests/Feature/Guards/ApiSurfaceGuardTest.php tests/Feature/Guards/OwnerScopeGuardTest.php
vendor/bin/pint --test app/Modules/Finance/Http tests/Feature/FinanceModule
git add app/Modules/Finance/Http tests/Feature/FinanceModule ../openapi.yaml
git commit -m "feat(finance): expose invoice payment and recurring APIs"
```

### Task 15: Build focused Vue invoice, payment, and recurring areas

**Files:**
- Create: `frontend/src/modules/finance/models/money.ts`
- Create: `frontend/src/modules/finance/models/invoice.ts`
- Create: `frontend/src/modules/finance/models/payment.ts`
- Create: `frontend/src/modules/finance/models/recurring.ts`
- Create: `frontend/src/modules/finance/api/invoices.ts`
- Create: `frontend/src/modules/finance/api/payments.ts`
- Create: `frontend/src/modules/finance/api/recurring.ts`
- Create: `frontend/src/modules/finance/stores/invoices.ts`
- Create: `frontend/src/modules/finance/stores/payments.ts`
- Create: `frontend/src/modules/finance/stores/recurring.ts`
- Create: `frontend/src/modules/finance/composables/useFinanceUrlFilters.ts`
- Create: `frontend/src/modules/finance/components/InvoiceLineEditor.vue`
- Create: `frontend/src/modules/finance/components/InvoiceTotals.vue`
- Create: `frontend/src/modules/finance/components/InvoiceActivity.vue`
- Create: `frontend/src/modules/finance/components/PaymentAllocationEditor.vue`
- Create: `frontend/src/modules/finance/components/AsyncStateBadge.vue`
- Create: `frontend/src/modules/finance/invoices/InvoiceListPage.vue`
- Create: `frontend/src/modules/finance/invoices/InvoiceDetailPage.vue`
- Create: `frontend/src/modules/finance/invoices/InvoiceEditorPage.vue`
- Create: `frontend/src/modules/finance/payments/PaymentListPage.vue`
- Create: `frontend/src/modules/finance/payments/PaymentDetailPage.vue`
- Create: `frontend/src/modules/finance/recurring/RecurringInvoiceListPage.vue`
- Create: `frontend/src/modules/finance/recurring/RecurringInvoiceEditorPage.vue`
- Create: `frontend/src/modules/finance/recurring/RecurringInvoiceRunsPage.vue`
- Create: `frontend/src/modules/finance/routes.ts`
- Create: `frontend/src/modules/finance/__tests__/money.test.ts`
- Create: `frontend/src/modules/finance/__tests__/invoice-workflow.test.ts`
- Create: `frontend/src/modules/finance/__tests__/payment-allocation.test.ts`
- Create: `frontend/src/modules/finance/__tests__/recurring-workflow.test.ts`
- Modify: `backend/lang/de/invoices.php`
- Modify: `backend/lang/en/invoices.php`
- Modify: `backend/lang/ru/invoices.php`

**Interfaces:**
- Consumes: Task 14 Resources and API errors.
- Produces: directly mountable feature pages; route activation waits for Task 17.

- [ ] **Step 1: Write failing exact money boundary tests**

```ts
expect(decimalToMinor('119.00')).toBe(11900);
expect(decimalToMinor('-0.01')).toBe(-1);
expect(() => decimalToMinor('1.001')).toThrow();
expect(minorToDecimal(11900)).toBe('119.00');
```

Keep form amounts and quantities as strings. Provisional client totals may use integer helpers for display, but save responses replace them with server totals and mismatch responses show the authoritative values.

- [ ] **Step 2: Write failing component/store workflow tests**

Mount pages with fake API responses and assert: filters persist in query parameters; conflict reloads current resource and asks for deliberate retry; save failure prevents finalize/send; finalize shows immutable PDF/revision; delivery failure exposes retry; partial allocation updates open amount; ambiguous suggestions require a selection; cancellation shows original unchanged; recurring run failure resumes from the displayed safe step.

- [ ] **Step 3: Implement focused APIs and stores**

Each store owns one paginated collection/detail, aborts stale requests, and exposes no global Finance snapshot. Route filters include invoice `q,status,kind,overdue,from,to,page`, payment `q,unallocated,from,to,page`, and recurring `status,mode,page`.

- [ ] **Step 4: Implement accessible feature pages**

Use existing `Btn`, `Badge`, `Card`, `Modal`, `Pager`, `Select`, and `TextField`. Disable immutable fields after finalization, expose revision PDF links, show async delivery/run truth rather than optimistic “sent”, require confirmation for allocation/cancellation/retry, and use monochrome icons only.

- [ ] **Step 5: Add complete EN/DE/RU translation parity**

Add literal keys for all statuses, actions, filters, errors, allocation reasons, recurrence intervals/modes/run states, and safe retry messages. Do not build translation keys dynamically unless every prefix is already covered by a static guard test.

- [ ] **Step 6: Run frontend gates and commit**

```bash
cd frontend
yarn test:js src/modules/finance
yarn typecheck
yarn lint
yarn build
cd ../backend
php artisan test tests/Feature/Guards/TranslationParityGuardTest.php tests/Feature/Guards/TranslationUsageGuardTest.php
git add ../frontend/src/modules/finance lang/de/invoices.php lang/en/invoices.php lang/ru/invoices.php
git commit -m "feat(finance): add invoice payment and recurring UI"
```

### Task 16: Migrate legacy invoices/payment links and prove exact compatibility

**Files:**
- Create: `backend/database/migrations/2026_08_28_110300_create_finance_invoice_migration_support.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Invoices/ImportLegacyInvoice.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyInvoiceMapper.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyInvoiceMigration.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyPaymentLinkMigration.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/InvoiceControlTotals.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyInvoiceReadProjection.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/InvoiceCutoverCheck.php`
- Create: `backend/app/Console/Commands/MigrateFinanceInvoiceSlice.php`
- Create: `backend/app/Console/Commands/CheckFinanceInvoiceCutover.php`
- Modify: `backend/app/Services/Backup/Sources/InvoiceBlobSource.php`
- Create: `backend/tests/Feature/FinanceModule/LegacyInvoiceMigrationTest.php`
- Create: `backend/tests/Feature/Backup/FinanceInvoiceBlobSourceTest.php`

**Interfaces:**
- Consumes: legacy `invoices`, `bank_transactions.invoice_id`, legacy PDFs/versions, historical parser, and all new commands.
- Produces: resumable source mappings/checkpoints, exact control totals, compatibility reads, and a hard cutover exit code.

- [ ] **Step 1: Write failing migration fixtures**

Create fixtures for draft/final/sent/paid, partial bank payment, overpayment, paid marker without a bank link, cancellation pair, soft-deleted numbered invoice, mixed tax/discount, imported invoice, multiple historical versions/PDFs, missing PDF, unreadable currency, conflicting number, cross-owner relation, and interrupted chunk. Run twice and assert no duplicate series, revisions, payments, allocations, PDFs, or activities.

- [ ] **Step 2: Define strict legacy mapping rules**

- Draft without number becomes one editable draft revision; no PDF is required.
- Numbered/final/sent/paid rows become immutable published revisions; every referenced PDF is copied to a new immutable path and SHA-256 checked.
- A legacy version is migrated only when its stored metadata/PDF can reconstruct and verify its snapshot; an incomplete published version stops that owner's phase with a stable diagnostic.
- `bank_transactions.invoice_id` becomes a payment source and allocation. Amount beyond invoice open remains unapplied; an insufficient link produces partial status.
- A paid legacy row without enough linked money receives one explicitly flagged `legacy_invoice_paid_marker` payment for the residual; it is never disguised as an imported bank transaction.
- Cancellation relations migrate originals first, then credit documents, without deleting allocations.
- Missing finalized PDF, unknown currency, totals mismatch, owner mismatch, duplicate number, or broken cancellation/version link stops readiness.

- [ ] **Step 3: Implement resumable, command-only migration**

Store per-owner phase, last source ID, status, and error code. Use `source_type/source_key` for idempotency and call `ImportLegacyInvoice`, `RecordPayment`, and `AllocatePayment`; adapters never insert directly into aggregate tables. Process deterministic chunks of 100 and commit each chunk.

- [ ] **Step 4: Compare exact controls**

For each owner/year/currency/source type compare record count, net/vat/gross minor sums, paid/open minor sums, number list, revision count/order, PDF count/hash, cancellation links, bank-payment links, quote/project source links, and stock movement references. `finance:check-invoice-cutover` exits non-zero on any mismatch or incomplete checkpoint.

- [ ] **Step 5: Preserve compatibility consumers and backups**

`LegacyInvoiceReadProjection` returns the old snapshot shape from new integer values only for still-legacy Home/report screens; it is read-only and marked for deletion by the global frontend cutover. Extend the backup source to include new immutable revision paths while retaining legacy paths until the final legacy-removal plan.

- [ ] **Step 6: Run migration, backup, and legacy regression tests**

```bash
cd backend
php artisan test tests/Feature/FinanceModule/LegacyInvoiceMigrationTest.php tests/Feature/Backup/FinanceInvoiceBlobSourceTest.php tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php tests/Feature/InvoiceVersionPdfTest.php tests/Feature/FinanceReportsTest.php tests/Feature/TaxReportsTest.php
vendor/bin/pint --test app/Modules/Finance/Infrastructure/Compatibility app/Console/Commands tests/Feature/FinanceModule/LegacyInvoiceMigrationTest.php
git add database/migrations/2026_08_28_110300_create_finance_invoice_migration_support.php app/Modules/Finance app/Console/Commands app/Services/Backup/Sources/InvoiceBlobSource.php tests/Feature/FinanceModule/LegacyInvoiceMigrationTest.php tests/Feature/Backup/FinanceInvoiceBlobSourceTest.php
git commit -m "feat(finance): migrate invoice and payment history"
```

### Task 17: Switch all invoice writers and perform the bounded cutover

**Files:**
- Modify: `backend/app/Http/Controllers/FinanceQuoteController.php`
- Modify: `backend/app/Http/Controllers/FinanceProjectPlanController.php`
- Modify: `backend/app/Http/Controllers/FinanceController.php`
- Modify: `backend/app/Services/Finance/FinanceReports.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyQuoteInvoiceSource.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyProjectTimeInvoiceSource.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/routes/web.php`
- Modify: `backend/app/Modules/Finance/Http/Routes/api.php`
- Modify: `frontend/src/router/index.ts`
- Modify: `frontend/src/views/Home.vue`
- Modify: `frontend/src/stores/finance.ts`
- Modify: `frontend/src/views/Finance.vue`
- Modify: `openapi.yaml`
- Modify: `backend/tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php`
- Create: `backend/tests/Feature/FinanceModule/InvoiceCutoverTest.php`
- Create: `frontend/src/modules/finance/__tests__/routes.test.ts`

**Interfaces:**
- Consumes: successful Task 16 controls, source contract, new pages/APIs, and compatibility read projection.
- Produces: canonical `/api/v1/finance` invoice/payment/recurring-invoice routes and no remaining runtime writes to legacy `invoices`.

- [ ] **Step 1: Add a failing static/runtime legacy-writer gate**

Scan runtime PHP outside migration/compatibility for `new Invoice`, `Invoice::create|forceCreate`, and direct updates to legacy invoice workflow fields. Exercise quote conversion and project-time billing and assert they call `CreateInvoiceDraftFromSource` with `quote_revision` and `project_time_batch` source metadata instead of creating legacy rows.

- [ ] **Step 2: Adapt quote conversion without importing quote logic into invoices**

The quote controller/module retains eligibility, current revision, expiry, acceptance, and conversion idempotency. When the new Quote module is available it passes the immutable quote revision directly. During the bounded legacy transition, `LegacyQuoteInvoiceSource` locks the sent legacy quote, canonicalizes that exact row into a frozen source snapshot, uses `sourceType='legacy_quote_snapshot'`, the quote ID as source revision identity, and its SHA-256, then calls the same invoice command. Store the returned invoice identity on the quote side in the surrounding transaction. No Invoice command reads or mutates a Quote model or re-evaluates quote status.

- [ ] **Step 3: Adapt project time and legacy reads**

Project billing owns time-entry selection/rate grouping and uses one source key/hash; the invoice command owns document creation/calculation. `LegacyProjectTimeInvoiceSource` locks the selected time entries in ascending ID order, invokes the nested invoice command inside the same outer database transaction, and stamps those entries with the returned invoice identity before commit; any stamp failure rolls the draft creation back. Move Home, reports, aging, and the temporary legacy Finance snapshot to `LegacyInvoiceReadProjection` so no post-cutover report reads stale legacy rows.

- [ ] **Step 4: Run the cutover gate before route changes**

Run:

```bash
cd backend
php artisan finance:migrate-invoice-slice --all-owners
php artisan finance:check-invoice-cutover
```

Expected: both exit zero, every owner phase is complete, controls match exactly, no unresolved writer is reported. Do not continue on a non-zero exit.

- [ ] **Step 5: Move preview API to canonical paths and activate Vue routes**

Register the module controllers at `/api/v1/finance/invoices`, `/payments`, `/payment-allocations`, `/recurring-invoice-templates`, and `/recurring-invoice-runs`; remove only their conflicting legacy routes. Keep legacy `/finance/recurring` subscription detection and `/finance/payment-methods` account management distinct. Activate lazy routes `/finance/invoices`, `/finance/invoices/:id`, `/finance/payments`, `/finance/payments/:id`, and `/finance/recurring-invoices`; keep unrelated legacy Finance sections on `Finance.vue` until the global frontend cutover.

- [ ] **Step 6: Remove bounded legacy invoice runtime code**

Delete invoice CRUD/finalize/storno/email/dun/PDF methods and their private helpers from `FinanceController`, remove invoice methods/types from the legacy Pinia store and invoice modal/list fragments from `Finance.vue`, and retain the legacy model/table solely for migration history. Do not remove historical migrations, quote/project/report code, payment-method accounts, bank transactions, or subscription detection.

- [ ] **Step 7: Replace preview OpenAPI paths and run cutover regression**

Remove preview path definitions, document canonical paths/operation IDs, and retain any explicitly supported compatibility response fields. Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule tests/Unit/Modules/Finance tests/Feature/FinanceQuoteTest.php tests/Feature/FinanceProjectPlanTest.php tests/Feature/FinanceReportsTest.php tests/Feature/TaxReportsTest.php tests/Feature/InvoiceDiscountTest.php tests/Feature/InvoiceDunTest.php tests/Feature/InvoiceEmailTest.php tests/Feature/InvoiceReminderTest.php tests/Feature/InvoiceStornoTest.php tests/Feature/InvoiceVersionPdfTest.php tests/Feature/Guards
vendor/bin/pint --test app/Modules/Finance app/Http/Controllers/FinanceController.php app/Http/Controllers/FinanceQuoteController.php app/Http/Controllers/FinanceProjectPlanController.php tests/Feature/FinanceModule
vendor/bin/phpstan analyse app/Modules/Finance app/Http/Controllers/FinanceQuoteController.php app/Http/Controllers/FinanceProjectPlanController.php --memory-limit=1G
cd ../frontend
yarn test:js src/modules/finance
yarn typecheck
yarn lint
yarn build
```

Expected: PASS; no legacy test is removed until its equivalent module/cutover assertion passes in the same commit.

- [ ] **Step 8: Commit the bounded cutover**

```bash
git add backend/app backend/routes backend/tests frontend/src openapi.yaml
git commit -m "refactor(finance): cut over invoice payment and recurring workflows"
```

### Task 18: Document operations, verify the complete strand, and hand off later dependencies

**Files:**
- Create: `docs/finance/invoices-payments-recurring.md`
- Modify: `docs/superpowers/plans/2026-08-28-finance-invoices-payments-recurring.md`

**Interfaces:**
- Consumes: Tasks 1–17.
- Produces: verified operational contract, failure/retry runbook, cutover evidence, and downstream integration notes.

- [ ] **Step 1: Document stable contracts and recovery procedures**

Document exact request/resource shapes, source contract, status projection, number allocator, signed allocation examples, cancellation/refund behavior, PDF storage/hash/streaming, delivery `failed|unknown` semantics, recurring month-end/timezone/catch-up limits, job attempts/backoff, migration commands/control totals, and rollback boundary. State that no push/tag/deploy occurs here.

- [ ] **Step 2: Run the full relevant backend and frontend suites**

```bash
cd backend
php artisan test tests/Feature/FinanceModule tests/Unit/Modules/Finance tests/Feature/FinanceQuoteTest.php tests/Feature/FinanceProjectPlanTest.php tests/Feature/FinanceRecurringTest.php tests/Feature/FinanceReportsTest.php tests/Feature/TaxReportsTest.php tests/Feature/FinanceProductTest.php tests/Feature/InvoiceDiscountTest.php tests/Feature/InvoiceDunTest.php tests/Feature/InvoiceEmailTest.php tests/Feature/InvoiceReminderTest.php tests/Feature/InvoiceStornoTest.php tests/Feature/InvoiceVersionPdfTest.php tests/Feature/Guards
vendor/bin/pint --test app/Modules/Finance tests/Feature/FinanceModule tests/Unit/Modules/Finance
vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G
composer audit
cd ../frontend
yarn test:js
yarn typecheck
yarn lint
yarn build
yarn audit --groups dependencies
```

Expected: every command exits zero.

- [ ] **Step 3: Run migration/cutover verification on realistic fixtures**

```bash
cd backend
php artisan migrate:fresh --env=testing --force
php artisan test tests/Feature/FinanceModule/LegacyInvoiceMigrationTest.php tests/Feature/FinanceModule/InvoiceCutoverTest.php
php artisan finance:check-invoice-cutover
```

Expected: repeatable migration, exact controls, zero unresolved writers, and a clean cutover result.

- [ ] **Step 4: Self-review against the design**

Map every design requirement in sections 3, 6, 7, 9, 10, 11, and 12 to a passing test/command; scan this plan and implementation for placeholder language; verify DTO field/type consistency; verify no float in Domain/Application; and inspect `git diff --check` plus `git status --short`.

- [ ] **Step 5: Mark only completed checks and commit documentation**

```bash
git add docs/finance/invoices-payments-recurring.md docs/superpowers/plans/2026-08-28-finance-invoices-payments-recurring.md
git commit -m "docs(finance): document invoice payment and recurring module"
```

## Cutover dependencies

1. Quote conversion must supply an immutable, owner-validated source revision to `CreateInvoiceDraftFromSource`; until the Quote rewrite supplies its native revision, the locked `LegacyQuoteInvoiceSource` freezes the eligible sent row. The invoice module never duplicates quote state or eligibility rules.
2. Project-time billing must switch from direct legacy `Invoice` writes to the same source contract before canonical invoice routes activate.
3. The product/stock adapter must support four-decimal quantities and its unique invoice reference before hardware invoices can finalize.
4. Company identity, numbering, and SMTP settings remain read through explicit ports; their snapshot values are frozen into the finalized revision.
5. The legacy migration/import work must repair any incomplete historical version/PDF before that owner can pass the cutover gate.
6. Global Finance frontend removal remains a later plan: this slice removes only invoice/payment/recurring fragments and keeps compatibility reads for unrelated legacy Finance sections.

## Principal risks and mitigations

- **Gapless numbering versus external PDF storage:** the database transaction can roll back the number but cannot atomically roll back object storage. Capability-owned paths plus orphan reconciliation prevent exposure/replacement and clean commit-failure leftovers.
- **SMTP cannot guarantee exactly-once delivery:** stable Message-ID, bounded attempts, and explicit `unknown` state prevent false success; a user chooses whether to retry an uncertain delivery.
- **Legacy version data may lack a reconstructable snapshot:** migration fails closed for that owner and reports the exact invoice/version/file; it never invents historical content.
- **Partial cutover has hidden legacy writers/readers:** the static writer scan, quote/project source adapters, report/Home compatibility projection, and non-zero cutover command make these dependencies executable gates.
- **Signed payments and credit notes are easy to invert:** domain tests pin positive invoice/incoming payment and negative credit/outgoing refund examples, while cross-sign allocations are rejected.
- **Scheduler backlog can overload a worker:** per-template/global claim caps bound one tick without dropping occurrences; persisted next-run state lets later ticks drain the backlog.
- **Timezone/month-end drift:** recurrence stores local anchor semantics and UTC execution separately and tests month-end, leap year, and DST transitions.
- **Frontend can reintroduce float authority:** form values remain strings, transport amounts are minor-unit integers, and the server resource always replaces provisional display totals.
