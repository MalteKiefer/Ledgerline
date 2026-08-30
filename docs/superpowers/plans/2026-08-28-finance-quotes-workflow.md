# Finance Quotes Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the modular quote workflow from editable draft through immutable revisions, server-rendered PDF, delivery, decision, duplication, expiry handling, and idempotent conversion into an invoice draft.

**Architecture:** A `finance_document_series` row remains the stable aggregate root. Quote-specific mutable draft state lives in a one-to-one extension, while every published customer-facing version is an immutable `finance_document_revision`; replacement is represented by append-only activity rather than revision mutation. HTTP controllers validate and authorize only, application commands own transactions and resumable side effects, infrastructure adapters own Eloquent, numbering, PDF, storage, mail, and the temporary legacy-invoice bridge, and the new Vue feature stays isolated below `frontend/src/modules/finance` until the later migration/cutover plan activates it.

**Tech Stack:** PHP 8.5, Laravel 13.8, Eloquent, PostgreSQL production, SQLite tests, PHPUnit 13, Dompdf 3, Flysystem, Laravel Mail/Queue, Vue 3.5, Pinia 4, Vue Router 5, TypeScript 6, Vitest 4.

**Spec:** `docs/superpowers/specs/2026-08-28-finance-module-rewrite-design.md`

## Global Constraints

- Authoritative money values use integer minor units and quantities use scale-4 integers; floating-point arithmetic is forbidden in Domain and Application code.
- Domain code must not import Laravel HTTP, Eloquent, filesystem, mail, queue, or Vue concerns.
- Every quote, draft, revision, activity, delivery, operation, and conversion is owner-scoped; a foreign identifier returns 404.
- Published revisions and their PDF path/hash never change. Supersession is an append-only `quote.revision.superseded` activity.
- Workflow state changes occur only through named commands and `QuoteWorkflow`, never generic CRUD updates.
- Only the current published, non-expired revision can be accepted, declined, or converted; accepted cannot become declined, and declined/replaced/expired revisions cannot convert.
- Client totals are optional control values only. A mismatch returns 422 `control_totals_mismatch`; the server result remains authoritative.
- Mutations return the current resource and optimistic `version`; conflicts return 409 `version_conflict` with the current resource.
- Create, publish, send, duplicate, accept, decline, and convert accept an `Idempotency-Key`; reuse with different input returns 409 `idempotency_key_reused`.
- Existing `/api/v1/finance/quotes*` routes and `Finance.vue` remain active until the later migration and frontend-cutover plans prove parity. This plan exposes additive `/api/v1/finance-v2/quotes*` routes and an unmounted frontend route export.
- No deep invoice finalization, stock, payment, project, recurring, bulk legacy migration, production cutover, deployment, push, version bump, or release tag occurs in this plan.

## Locked workflow and API decisions

- Persisted series states are `draft`, `sent`, `accepted`, `declined`, and `converted`. Expiry is derived from the current revision's `valid_until` at read/command time and is returned as `effective_status: expired`; no scheduler writes an `expired` state.
- A first publication performs `draft -> sent`. A later version may be opened only while the series is `sent` (including effectively expired), keeps the current published revision externally valid while edited, and returns the series to `sent` when published. Accepted, declined, and converted series can be duplicated into a new series but cannot receive a new in-series version.
- While an unpublished later version exists, accept/decline/convert return 409 `quote_draft_pending`; the user must publish or discard that draft explicitly. This prevents deciding one revision while editing another.
- The first publication allocates the configured base number, for example `AN-2026-0007`. Later revisions keep that stable base number and expose `revision_label` as `AN-2026-0007-R2`, `-R3`, and so on. This preserves the existing number template while making every PDF identity unambiguous.
- `POST /publish` creates and publishes the immutable revision without email. `POST /send` resumes publication when necessary, then queues delivery of exactly that revision. A mail failure never rolls back the number/revision/PDF and is visible as a failed delivery plus `quote.mail.failed` activity.
- SMTP cannot guarantee exactly-once delivery after a remote server accepts a message but the worker dies before persisting success. The application guarantees no second send after a recorded success for the same key and exposes this residual at-least-once risk in the activity/delivery state.
- Quote-to-invoice conversion targets only an accepted, current, non-expired revision. `QuoteToInvoicePort` receives the immutable snapshot and source revision identity. The initial adapter creates one legacy `Invoice` draft; the invoice rewrite replaces that binding without changing quote commands or API responses.

---

### Task 1: Characterize the legacy boundary and enforce module dependency direction

**Files:**
- Modify: `backend/tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php`
- Modify: `backend/tests/Feature/FinanceModule/FinanceModuleBootstrapTest.php`
- Create: `backend/tests/Feature/FinanceModule/Quotes/LegacyQuoteCompatibilityTest.php`

**Interfaces:**
- Consumes: legacy routes `api.finance.quotes.*`, `FinanceQuote`, `QuoteMail`, and the current finance snapshot.
- Produces: a frozen parity list and a mechanical rule that new Quote Domain/Application code cannot import legacy controllers/models or framework concerns.

- [ ] **Step 1: Add failing characterization tests**

Cover the current observable baseline: draft defaults, configured numbering, number reuse prevention after soft delete, owner 404, optimistic conflict, client PDF upload/replacement, SMTP prerequisites, derived expiry, duplicate, project conversion, and invoice conversion idempotency. Add explicit assertions documenting the known gaps: decisions currently allow illegal reversals, client totals are trusted, and PDF bytes can be replaced.

- [ ] **Step 2: Extend the module source guard**

Scan `Domain/Quotes` and `Application` and reject these imports:

```php
$forbidden = [
    'App\\Http\\Controllers',
    'App\\Models\\FinanceQuote',
    'App\\Models\\Invoice',
    'Illuminate\\Http',
    'Illuminate\\Database\\Eloquent',
    'Illuminate\\Support\\Facades',
];
```

Allow legacy model access only in named adapters below `Infrastructure/Compatibility`.

- [ ] **Step 3: Run the baseline**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php tests/Feature/FinanceModule/Quotes/LegacyQuoteCompatibilityTest.php tests/Feature/FinanceQuoteTest.php
```

Expected: existing behavior assertions pass; gap assertions demonstrate why the new shadow workflow is still required.

- [ ] **Step 4: Commit**

```bash
git add backend/tests/Feature/FinanceModule
git commit -m "test(finance): freeze legacy quote compatibility"
```

### Task 2: Define quote domain values and workflow rules

**Files:**
- Create: `backend/app/Modules/Finance/Domain/Quotes/QuoteStatus.php`
- Create: `backend/app/Modules/Finance/Domain/Quotes/QuoteRevisionState.php`
- Create: `backend/app/Modules/Finance/Domain/Quotes/QuoteWorkflow.php`
- Create: `backend/app/Modules/Finance/Domain/Quotes/QuoteNumber.php`
- Create: `backend/app/Modules/Finance/Domain/Quotes/Exception/InvalidQuoteAction.php`
- Create: `backend/tests/Unit/Modules/Finance/Domain/Quotes/QuoteWorkflowTest.php`

**Interfaces:**
- Consumes: shared `StateMachine` and an injected `DateTimeImmutable` for expiry checks.
- Produces: `QuoteWorkflow::assertDraftEditable`, `assertVersionMayStart`, `assertCurrentRevisionMayBeDecided`, and `assertCurrentRevisionMayBeConverted` with stable error codes.

- [ ] **Step 1: Write failing state-machine tests**

Test first publish, later-version publish, `sent -> accepted|declined`, `accepted -> converted`, rejection of reverse/self transitions, and terminal accepted/declined/converted behavior. Test that an expired, replaced, stale, or non-current revision cannot be decided or converted and that a pending draft blocks decision/conversion.

```php
$workflow->assertCurrentRevisionMayBeConverted(
    QuoteStatus::Accepted,
    expectedRevisionId: 41,
    currentRevisionId: 41,
    validUntil: new DateTimeImmutable('2026-09-30'),
    now: new DateTimeImmutable('2026-09-01'),
    hasPendingDraft: false,
);
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Unit/Modules/Finance/Domain/Quotes/QuoteWorkflowTest.php`

Expected: FAIL because the quote domain types do not exist.

- [ ] **Step 3: Implement exact rules and errors**

`InvalidQuoteAction` exposes `public readonly string $errorCode`. Use exactly: `quote_locked`, `quote_not_published`, `quote_revision_stale`, `quote_revision_replaced`, `quote_expired`, `quote_draft_pending`, `quote_not_accepted`, and `invalid_transition`. `QuoteNumber` validates a non-empty base number and returns the base for revision 1 and `{$base}-R{$revision}` thereafter.

- [ ] **Step 4: Run unit tests and formatting**

Run:

```bash
cd backend
php artisan test tests/Unit/Modules/Finance/Domain/Quotes
vendor/bin/pint --test app/Modules/Finance/Domain/Quotes tests/Unit/Modules/Finance/Domain/Quotes
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/Finance/Domain/Quotes backend/tests/Unit/Modules/Finance/Domain/Quotes
git commit -m "feat(finance): define quote workflow rules"
```

### Task 3: Add quote aggregate, draft, delivery, operation, conversion, and number-sequence schema

**Files:**
- Create: `backend/database/migrations/2027_03_03_100000_create_finance_quote_workflow.php`
- Create: `backend/tests/Feature/FinanceModule/Quotes/QuoteSchemaTest.php`

**Interfaces:**
- Consumes: `finance_document_series`, `finance_document_revisions`, `finance_partners`, `invoices`, and `users` migrations.
- Produces: `finance_quote_series`, `finance_quote_drafts`, `finance_quote_number_sequences`, `finance_quote_operations`, `finance_quote_deliveries`, and `finance_quote_conversions`.

- [ ] **Step 1: Write failing schema and integrity tests**

Assert composite owner/series/revision foreign keys, positive versions and sequences, allowed operation/delivery states, uniqueness, and deletion rules. Prove that cross-owner current revisions, draft bases, deliveries, and conversions are rejected by both PostgreSQL-compatible constraints and SQLite test triggers.

- [ ] **Step 2: Implement the additive schema**

Use these ownership and concurrency columns:

```text
finance_quote_series:
  document_series_id PK, user_id, partner_id nullable,
  current_revision_id nullable, number nullable, sequence_year nullable,
  sequence_number nullable, version unsigned default 0,
  published_at nullable, accepted_at nullable, declined_at nullable,
  converted_at nullable, deleted_at nullable, created_at, updated_at

finance_quote_drafts:
  document_series_id PK, user_id, based_on_revision_id nullable,
  payload json, net_minor bigint, vat_minor bigint, gross_minor bigint,
  currency char(3), updated_by nullable, created_at, updated_at

finance_quote_number_sequences:
  user_id, year, next_sequence; unique(user_id, year)

finance_quote_operations:
  id, user_id, document_series_id nullable, operation, idempotency_key,
  request_sha256, state(reserved|running|succeeded|failed), result json nullable,
  error_code nullable, started_at, completed_at nullable;
  unique(user_id, operation, idempotency_key)

finance_quote_deliveries:
  id, user_id, document_series_id, document_revision_id, recipient,
  recipient_domain, message_id, state(queued|sending|sent|failed), attempts,
  last_error_code nullable, queued_at, sent_at nullable, failed_at nullable

finance_quote_conversions:
  id, user_id, document_series_id, source_revision_id,
  target_type(invoice), target_reference, target_id nullable, created_at;
  unique(user_id, source_revision_id, target_type)
```

Make `(user_id, sequence_year, sequence_number)` unique even after soft deletion so an issued number is never reused. The quote extension and draft must reference the same owner as the generic series; current/base/source revision references must belong to that same series.

- [ ] **Step 3: Run the migration cycle**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/Quotes/QuoteSchemaTest.php
php artisan migrate:fresh --env=testing --force
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add backend/database/migrations/2027_03_03_100000_create_finance_quote_workflow.php backend/tests/Feature/FinanceModule/Quotes/QuoteSchemaTest.php
git commit -m "feat(finance): add quote workflow schema"
```

### Task 4: Add focused records, repositories, DTOs, and idempotency reservations

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/QuoteId.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/QuoteRevisionRef.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/QuoteView.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/QuotePage.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/OperationReservation.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Quotes/QuoteRepository.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Quotes/QuoteOperationRepository.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Quotes/QuoteReferenceResolver.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Quotes/QuoteSettings.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Clock.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Quotes/GetQuote.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Quotes/ListQuotes.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Quotes/ListQuoteRevisions.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/QuoteSeriesRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/QuoteDraftRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/QuoteOperationRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/QuoteDeliveryRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/QuoteConversionRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentQuoteRepository.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentQuoteOperationRepository.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentQuoteReferenceResolver.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Settings/EloquentQuoteSettings.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Time/SystemClock.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/tests/Feature/FinanceModule/Quotes/QuotePersistenceTest.php`

**Interfaces:**
- Consumes: Task 3 schema, foundation records, legacy partner/product tables through Infrastructure adapters, and owner settings through a port.
- Produces: owner-scoped aggregate reads, ordered revision history, paginated filters, version compare-and-swap, reference ownership checks, deterministic clock/settings access, and idempotency replay/reuse decisions.

- [ ] **Step 1: Write failing owner, pagination, and concurrency tests**

Test owner 404s for UUIDs and numeric child IDs, query/status/effective-expiry/date filters, stable `published_at DESC, id DESC` pagination, and a compare-and-swap update that returns current `QuoteView` on version mismatch.

- [ ] **Step 2: Write failing idempotency tests**

The repository contract is:

```php
public function reserve(
    int $ownerId,
    string $operation,
    string $key,
    string $requestSha256,
    ?QuoteId $quoteId,
): OperationReservation;

public function succeed(OperationReservation $reservation, array $result): void;
public function fail(OperationReservation $reservation, string $errorCode): void;
```

Assert first reservation is new, a concurrent reservation reports `in_progress`, a completed replay returns stored result, and the same key with another request hash throws `idempotency_key_reused`.

- [ ] **Step 3: Define repository and query signatures**

```php
interface QuoteRepository
{
    public function get(QuoteId $id): QuoteView;
    public function page(array $filters, int $page, int $perPage): QuotePage;
    public function revisions(QuoteId $id): array;
    public function createDraft(int $ownerId, array $payload, DocumentTotals $totals): QuoteView;
    public function updateDraft(QuoteId $id, int $expectedVersion, array $payload, DocumentTotals $totals): QuoteView;
}
```

`QuoteReferenceResolver` exposes `assertOwnedPartner(?int)` and `assertOwnedProducts(list<int>)`. `QuoteSettings` returns quote number format/floor, default validity days, invoice payment terms, owner timezone, and sender identity without exposing SMTP credentials. `Clock::now(): DateTimeImmutable` is injected into time-sensitive commands. Queries are readonly wrappers around the repository and return only application DTOs.

- [ ] **Step 4: Implement records and adapters**

Records expose relations only and keep owner IDs, number fields, current revision, operation result, conversion target, and delivery state out of `$fillable`. Repository transactions lock in this order: document series, quote extension, current/draft revision, number-sequence row. Idempotency reservation uses the unique constraint rather than an application-only existence check.

- [ ] **Step 5: Bind ports and run checks**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/Quotes/QuotePersistenceTest.php
vendor/bin/pint --test app/Modules/Finance/Application app/Modules/Finance/Infrastructure/Persistence tests/Feature/FinanceModule/Quotes
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance backend/tests/Feature/FinanceModule/Quotes/QuotePersistenceTest.php
git commit -m "feat(finance): persist owner scoped quote aggregates"
```

### Task 5: Parse quote inputs and implement server-authoritative draft commands

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/QuoteDraftData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/QuoteLineData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/QuoteTotalsView.php`
- Create: `backend/app/Modules/Finance/Application/Services/Quotes/QuoteDraftFactory.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Quotes/CreateQuote.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Quotes/UpdateQuoteDraft.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Quotes/DiscardQuoteDraft.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Quotes/PreviewQuoteTotals.php`
- Create: `backend/tests/Feature/FinanceModule/Quotes/QuoteDraftApplicationTest.php`

**Interfaces:**
- Consumes: decimal-string request data, `DocumentCalculator`, `QuoteRepository`, and owner-validated partner/product references.
- Produces: authoritative draft snapshots with no floats and commands returning `QuoteView`.

- [ ] **Step 1: Write failing input and total tests**

Use line wire fields `description`, `quantity`, `unit`, `unit_price`, `tax_rate`, `kind`, and `product_id`. Accept decimal strings only (`quantity` scale 4, money scale 2, tax percent scale 2 converted exactly to basis points). Reject JSON numbers, mixed currencies, foreign partner/product IDs, empty lines, discounts outside the shared calculator contract, and more than 200 lines.

```php
new QuoteDraftData(
    title: 'Network refresh',
    partnerId: 7,
    customer: ['name' => 'Ada GmbH', 'email' => 'billing@example.com'],
    issueDate: '2026-08-28',
    validUntil: '2026-09-27',
    currency: 'EUR',
    lines: [new QuoteLineData('Consulting', '2.5000', 'hour', '100.00', '19.00', 'service', 12)],
    discountType: 'percent',
    discountValue: '10.00',
    introText: null,
    outroText: null,
    internalNote: null,
    controlNetMinor: 22500,
    controlVatMinor: 4275,
    controlGrossMinor: 26775,
);
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/FinanceModule/Quotes/QuoteDraftApplicationTest.php`

Expected: FAIL because DTOs and commands do not exist.

- [ ] **Step 3: Implement draft creation/update/preview**

`QuoteDraftFactory` converts strings into `Money`, `DecimalQuantity`, `DocumentLine`, and `Discount`, calculates totals, replaces all caller totals in the stored payload, and emits `quote.created`/`quote.draft.updated`. `UpdateQuoteDraft` requires expected version and updates only a mutable draft. `DiscardQuoteDraft` is allowed only for a later-version draft and emits `quote.draft.discarded`; deleting the only initial draft is outside this plan.

- [ ] **Step 4: Run focused and calculator tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/Quotes/QuoteDraftApplicationTest.php tests/Unit/Modules/Finance/Domain/Shared/DocumentCalculatorTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/Finance/Application backend/tests/Feature/FinanceModule/Quotes/QuoteDraftApplicationTest.php
git commit -m "feat(finance): add authoritative quote drafts"
```

### Task 6: Implement numbering, immutable publication, and in-series versions

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/PublishQuoteData.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Quotes/QuoteNumberAllocator.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Quotes/StartQuoteVersion.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Quotes/PublishQuote.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/DatabaseQuoteNumberAllocator.php`
- Modify: `backend/app/Modules/Finance/Application/Commands/CreateDocumentRevision.php`
- Modify: `backend/app/Modules/Finance/Application/Commands/PublishDocumentRevision.php`
- Modify: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentDocumentRevisionRepository.php`
- Create: `backend/tests/Feature/FinanceModule/Quotes/QuotePublicationTest.php`

**Interfaces:**
- Consumes: draft snapshot, foundation revision commands/ports, `UserSetting::quote_number_format`, `quote_next_number`, and `DocumentNumber` inside Infrastructure only.
- Produces: `StartQuoteVersion::handle(QuoteId,int): QuoteView` and `PublishQuote::handle(PublishQuoteData): QuoteView`.

- [ ] **Step 1: Write failing publication tests**

Assert first number allocation, configured floor/template, concurrent publication of two series, retry without a second number/revision/render, preservation of deleted numbers, canonical snapshot contents, and rollback/resume behavior for validation, revision creation, rendering, storage, and final aggregate-state failures.

- [ ] **Step 2: Write failing version tests**

Assert that `StartQuoteVersion` copies the current immutable snapshot into a separate draft, sets `based_on_revision_id`, preserves the current sent revision, refuses a second pending draft, and refuses accepted/declined/converted series. On publication, assert revision N points to N-1, the old bytes/hash stay unchanged, and `quote.revision.superseded` references both IDs.

- [ ] **Step 3: Extract a reusable canonical snapshot builder**

Move the private canonicalization/authoritative-snapshot work from `CreateDocumentRevision` into `Application/Services/CanonicalDocumentSnapshot.php`. Keep the existing public command signature unchanged so foundation callers remain compatible. The quote snapshot must include:

```text
schema_version, document_type=quote, series_uuid, document_number,
revision_number, revision_label, title, customer, partner_id,
issue_date, valid_until, currency, lines, discount, totals,
intro_text, outro_text, customer_note
```

- [ ] **Step 4: Implement resumable publication**

Reserve the idempotency operation, lock the aggregate, allocate the base number once, create the revision once, publish through the foundation port, and then set `current_revision_id`, `status=sent`, and `published_at`. Store checkpoint IDs in the operation result so retry completes after a crash between transactions without creating another number, revision, or PDF. Never mail from this command.

- [ ] **Step 5: Run publication, foundation, and concurrency tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/Quotes/QuotePublicationTest.php tests/Feature/FinanceModule/DocumentRevisionApplicationTest.php
```

Expected: PASS with one number and one published revision per idempotency key.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance backend/tests/Feature/FinanceModule/Quotes/QuotePublicationTest.php
git commit -m "feat(finance): publish immutable quote revisions"
```

### Task 7: Provide production PDF rendering, immutable storage, and secure streaming

**Files:**
- Modify: `backend/composer.json`
- Modify: `backend/composer.lock`
- Create: `backend/app/Modules/Finance/Infrastructure/Pdf/BladeDocumentRenderer.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Pdf/QuotePdfViewModel.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Pdf/FlysystemDocumentStorage.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Quotes/QuoteRevisionPdfController.php`
- Create: `backend/resources/views/finance/quotes/pdf.blade.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/tests/Feature/FinanceModule/Quotes/QuotePdfTest.php`

**Interfaces:**
- Consumes: immutable canonical snapshot and foundation `DocumentRenderer`/`DocumentStorage` ports.
- Produces: deterministic PDF bytes, capability-owned object paths, and authorized immutable streaming.

- [ ] **Step 1: Add the renderer dependency**

Run: `cd backend && composer require dompdf/dompdf:^3.1 --no-interaction`

Expected: `composer.json` and `composer.lock` contain Dompdf 3.x and no unrelated package changes.

- [ ] **Step 2: Write failing renderer/storage/stream tests**

Assert `%PDF-` output, escaped customer content, visible number/revision/validity/tax totals, no payment QR, path format `finance/revisions/{token-prefix}/{ownership-token}.pdf`, byte SHA-256 equality, cleanup by ownership token only, owner 404, immutable ETag, inline/download dispositions, `application/pdf`, `nosniff`, sandbox CSP, and private immutable caching.

- [ ] **Step 3: Implement adapters**

`BladeDocumentRenderer` dispatches on `document_type` and currently accepts only `quote`. Render only canonical snapshot values. `FlysystemDocumentStorage` writes a new private object per publication through the configured `files.disk`; it never overwrites or content-deduplicates. `delete($ownershipToken)` derives only that token's path and cannot delete an object with another capability.

- [ ] **Step 4: Bind production ports and run tests**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule/Quotes/QuotePdfTest.php tests/Feature/FinanceModule/DocumentRevisionApplicationTest.php
vendor/bin/pint --test app/Modules/Finance/Infrastructure/Pdf app/Modules/Finance/Http/Controllers/Quotes tests/Feature/FinanceModule/Quotes
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/composer.json backend/composer.lock backend/app/Modules/Finance backend/resources/views/finance/quotes backend/tests/Feature/FinanceModule/Quotes/QuotePdfTest.php
git commit -m "feat(finance): render and stream quote revision PDFs"
```

### Task 8: Implement queued delivery and safe retry

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/SendQuoteData.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Quotes/QuoteMailer.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Quotes/SendQuote.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Mail/LaravelQuoteMailer.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Mail/CompanySmtpMailer.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Mail/QuoteRevisionMail.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Mail/Jobs/DeliverQuoteRevision.php`
- Create: `backend/resources/views/emails/finance-quote.blade.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/tests/Feature/FinanceModule/Quotes/QuoteDeliveryTest.php`

**Interfaces:**
- Consumes: published revision PDF, recipient/default customer email, owner SMTP settings, and `PublishQuote` for a pending draft.
- Produces: `SendQuote::handle(SendQuoteData): QuoteView`, durable `delivery_status`, and retryable queue jobs with stable message IDs.

- [ ] **Step 1: Write failing delivery tests**

Test send-with-publication ordering, existing-revision send, recipient fallback, `no_recipient`, `no_smtp`, missing PDF, owner scope, same-key replay, concurrent same-key requests, and that mail is never attempted after validation/render/storage failure.

- [ ] **Step 2: Write failing worker tests**

Assert `afterCommit` dispatch, exact immutable PDF attachment, `Message-ID` derived from delivery UUID, recipient address absent from logs/activities, success activity, three bounded attempts with backoff `[60, 300, 900]`, final failed activity, and retry of a failed delivery without creating another revision/PDF.

- [ ] **Step 3: Extract company SMTP configuration**

Move the reusable runtime configuration and guaranteed `finally` cleanup from `FinanceController::companyMailer()`/`forgetCompanyMailer()` into `CompanySmtpMailer`. Keep the legacy controller behavior unchanged by delegating its existing invoice/quote calls to this service; retain the `OutboundUrl::hostAllowed` egress guard and 15-second timeout.

- [ ] **Step 4: Implement application and job flow**

`SendQuote` publishes first when a draft exists, creates/reuses a delivery for the exact `current_revision_id`, records `quote.mail.queued`, and returns 202-compatible state. The job changes `queued -> sending -> sent|failed`, writes secret-free activities, and never changes revision/PDF metadata.

- [ ] **Step 5: Run quote and legacy email tests**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule/Quotes/QuoteDeliveryTest.php tests/Feature/FinanceQuoteTest.php tests/Feature/InvoiceEmailTest.php tests/Feature/InvoiceReminderTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance backend/app/Http/Controllers/FinanceController.php backend/resources/views/emails backend/tests/Feature/FinanceModule/Quotes/QuoteDeliveryTest.php
git commit -m "feat(finance): deliver quote revisions safely"
```

### Task 9: Implement decisions, expiry semantics, duplication, and invoice conversion boundary

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/DecideQuoteData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/DuplicateQuoteData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/ConvertQuoteToInvoiceData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Quotes/InvoiceDraftTarget.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Quotes/QuoteToInvoicePort.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Quotes/AcceptQuote.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Quotes/DeclineQuote.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Quotes/DuplicateQuote.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Quotes/ConvertQuoteToInvoice.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyInvoiceDraftAdapter.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/tests/Feature/FinanceModule/Quotes/QuoteDecisionConversionTest.php`

**Interfaces:**
- Consumes: exact expected revision ID, immutable source snapshot, operation repository, and invoice target port.
- Produces: terminal decisions, independent draft copies, unique conversion rows, and an invoice target DTO without invoice workflow knowledge.

- [ ] **Step 1: Write failing decision/expiry tests**

Cover accepted and declined timestamps/activities, idempotent replay, accepted-to-declined rejection, stale revision, replaced revision, pending draft, and validity boundaries at the owner's date (`valid_until` is valid through 23:59:59 in the configured timezone). Assert `effective_status=expired` without a database status update.

- [ ] **Step 2: Write failing duplicate tests**

Duplicate the selected current revision or initial draft into a new UUID with no number, no publication/conversion state, today's issue date, validity from `quote_valid_days`, copied business content, and authoritative recalculation. The same key returns the same new series; a new key creates another intentional duplicate.

- [ ] **Step 3: Write failing conversion tests**

Assert only accepted current non-expired revisions convert, conversion is unique under concurrent different keys, retry returns the same target, foreign target IDs cannot be injected, and the port receives source series UUID, revision ID/hash, number/label, customer, lines, discount, totals, notes, and partner reference. Assert no stock move, invoice number, finalization, or payment row is created.

```php
interface QuoteToInvoicePort
{
    public function createDraft(
        int $ownerId,
        QuoteRevisionRef $source,
        array $immutableSnapshot,
    ): InvoiceDraftTarget;
}
```

- [ ] **Step 4: Implement the temporary legacy adapter**

Create one owner-scoped legacy `Invoice` with `status=draft`, `type=invoice`, no number, due date from `invoice_payment_terms_days`, and values copied from the immutable server snapshot. Return `InvoiceDraftTarget(targetReference: 'legacy-invoice:{id}', targetId: $id)`. Keep all legacy imports inside this adapter so the later invoice module replaces one binding.

- [ ] **Step 5: Run focused and legacy conversion tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/Quotes/QuoteDecisionConversionTest.php tests/Feature/FinanceQuoteTest.php tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance backend/tests/Feature/FinanceModule/Quotes/QuoteDecisionConversionTest.php
git commit -m "feat(finance): add quote decisions and invoice conversion port"
```

### Task 10: Expose thin HTTP controllers, stable resources, pagination, and OpenAPI

**Files:**
- Create: `backend/app/Modules/Finance/Http/Requests/Quotes/QuoteDraftRequest.php`
- Create: `backend/app/Modules/Finance/Http/Requests/Quotes/QuoteListRequest.php`
- Create: `backend/app/Modules/Finance/Http/Requests/Quotes/QuoteActionRequest.php`
- Create: `backend/app/Modules/Finance/Http/Requests/Quotes/SendQuoteRequest.php`
- Create: `backend/app/Modules/Finance/Http/Resources/Quotes/QuoteResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/Quotes/QuoteRevisionResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/Quotes/QuoteDeliveryResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/Quotes/QuotePageResource.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Quotes/QuoteController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Quotes/QuoteDraftController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Quotes/QuotePublicationController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Quotes/QuoteDeliveryController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Quotes/QuoteDecisionController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Quotes/QuoteDuplicationController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Quotes/QuoteInvoiceConversionController.php`
- Modify: `backend/app/Modules/Finance/Http/Routes/api.php`
- Modify: `openapi.yaml`
- Create: `backend/tests/Feature/FinanceModule/Quotes/QuoteApiTest.php`

**Interfaces:**
- Consumes: Tasks 5–9 commands/queries.
- Produces: additive `/api/v1/finance-v2/quotes` API and exact OpenAPI schemas.

- [ ] **Step 1: Write failing request/resource/API tests**

Cover authentication/module gate/throttles, owner 404, 422 field errors and machine codes, 409 version/stale/idempotency conflicts, 202 delivery response, 200 idempotent replay, stable resource types, pagination links/meta, URL filters, revision history, and no `pdf_path`, SMTP secret, raw operation result, or legacy `user_id` leakage.

- [ ] **Step 2: Register the route surface**

Use these routes and names:

```text
GET    /finance-v2/quotes                         api.finance-v2.quotes.index
POST   /finance-v2/quotes/preview                 api.finance-v2.quotes.preview
POST   /finance-v2/quotes                         api.finance-v2.quotes.store
GET    /finance-v2/quotes/{quote}                 api.finance-v2.quotes.show
PUT    /finance-v2/quotes/{quote}/draft           api.finance-v2.quotes.draft.update
DELETE /finance-v2/quotes/{quote}/draft           api.finance-v2.quotes.draft.discard
POST   /finance-v2/quotes/{quote}/versions        api.finance-v2.quotes.versions.store
POST   /finance-v2/quotes/{quote}/publish         api.finance-v2.quotes.publish
POST   /finance-v2/quotes/{quote}/send            api.finance-v2.quotes.send
POST   /finance-v2/quotes/{quote}/accept          api.finance-v2.quotes.accept
POST   /finance-v2/quotes/{quote}/decline         api.finance-v2.quotes.decline
POST   /finance-v2/quotes/{quote}/duplicate       api.finance-v2.quotes.duplicate
POST   /finance-v2/quotes/{quote}/conversions/invoice api.finance-v2.quotes.convert.invoice
GET    /finance-v2/quotes/{quote}/revisions/{revision}/pdf api.finance-v2.quotes.revisions.pdf
```

Use UUID route keys. Require `Idempotency-Key` on create and named action routes, `expected_revision_id` on decision/conversion, and `version` on draft update/start/discard.

- [ ] **Step 3: Implement thin adapters and error mapping**

Each controller calls one command/query. Map `InvalidQuoteAction` codes to 409 or 422 exactly as documented; map model-not-found to 404. `QuoteResource` returns `id` as UUID, `status`, `effective_status`, `version`, `has_pending_draft`, current revision summary, draft, totals as integer minor units plus currency, conversions, delivery status, and capability URLs.

- [ ] **Step 4: Replace the legacy OpenAPI quote contract with parallel legacy/v2 names**

Keep existing legacy paths documented. Add `FinanceV2Quote`, `FinanceV2QuoteDraftInput`, `FinanceV2QuoteRevision`, `FinanceV2QuoteDelivery`, `FinanceV2Money`, `FinanceV2QuotePage`, the routes above, `Idempotency-Key` headers, exact status/error responses, and integer/scaled numeric formats. Do not describe client PDF upload on v2.

- [ ] **Step 5: Run API guards and quote suites**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule/Quotes/QuoteApiTest.php tests/Feature/Guards/ApiSurfaceGuardTest.php tests/Feature/FinanceModule tests/Unit/Modules/Finance
```

Expected: PASS and every new route appears in `openapi.yaml`.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance/Http openapi.yaml backend/tests/Feature/FinanceModule/Quotes/QuoteApiTest.php
git commit -m "feat(finance): expose modular quote API"
```

### Task 11: Build the isolated frontend quote API, models, store, and URL filters

**Files:**
- Create: `frontend/src/modules/finance/models/money.ts`
- Create: `frontend/src/modules/finance/models/quote.ts`
- Create: `frontend/src/modules/finance/api/quoteApi.ts`
- Create: `frontend/src/modules/finance/stores/quotes.ts`
- Create: `frontend/src/modules/finance/composables/useQuoteFilters.ts`
- Create: `frontend/src/modules/finance/api/__tests__/quoteApi.test.ts`
- Create: `frontend/src/modules/finance/stores/__tests__/quotes.test.ts`
- Create: `frontend/src/modules/finance/composables/__tests__/useQuoteFilters.test.ts`
- Modify: `frontend/src/api/client.ts`
- Modify: `frontend/src/api/__tests__/client.test.ts`

**Interfaces:**
- Consumes: Task 10 OpenAPI wire contract.
- Produces: typed API methods, a focused paginated Pinia store, stable idempotency keys per user action, and URL-owned filter state.

- [ ] **Step 1: Write failing API/model tests**

Assert decimal form values remain strings, totals remain integer minor units, UUID IDs remain strings, `Idempotency-Key` reaches fetch headers, and API methods target only `/api/v1/finance-v2/quotes`. Add `VersionConflict<T>` support for `current` while preserving existing callers that read `version`.

- [ ] **Step 2: Write failing store/filter tests**

Test `q`, `status`, `effective_status`, `sort`, `direction`, and `page` round-trip through `route.query`; changes reset page to 1. Test request cancellation/stale-response suppression, page replacement, current-resource upsert, conflict replacement from `error.current`, and retry reusing the same action key until success/final cancellation.

- [ ] **Step 3: Implement the focused client layer**

Expose `list`, `show`, `preview`, `create`, `updateDraft`, `discardDraft`, `startVersion`, `publish`, `send`, `accept`, `decline`, `duplicate`, `convertToInvoice`, and `revisionPdfUrl`. Generate keys with `crypto.randomUUID()` at the UI action boundary, not inside each retrying HTTP call.

- [ ] **Step 4: Run frontend unit gates**

Run:

```bash
cd frontend
yarn test:js src/modules/finance src/api/__tests__/client.test.ts
yarn typecheck
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/modules/finance frontend/src/api/client.ts frontend/src/api/__tests__/client.test.ts
git commit -m "feat(finance): add modular quote client state"
```

### Task 12: Build quote pages, revision history, and truthful workflow feedback

**Files:**
- Create: `frontend/src/modules/finance/components/quotes/QuoteStatusBadge.vue`
- Create: `frontend/src/modules/finance/components/quotes/QuoteLineEditor.vue`
- Create: `frontend/src/modules/finance/components/quotes/QuoteTotals.vue`
- Create: `frontend/src/modules/finance/components/quotes/QuoteRevisionTimeline.vue`
- Create: `frontend/src/modules/finance/components/quotes/QuoteWorkflowActions.vue`
- Create: `frontend/src/modules/finance/quotes/QuoteListPage.vue`
- Create: `frontend/src/modules/finance/quotes/QuoteDetailPage.vue`
- Create: `frontend/src/modules/finance/quotes/QuoteEditPage.vue`
- Create: `frontend/src/modules/finance/quotes/routes.ts`
- Create: `frontend/src/modules/finance/quotes/__tests__/QuoteListPage.test.ts`
- Create: `frontend/src/modules/finance/quotes/__tests__/QuoteDetailPage.test.ts`
- Create: `frontend/src/modules/finance/quotes/__tests__/QuoteEditPage.test.ts`
- Modify: `backend/lang/de/invoices.php`
- Modify: `backend/lang/en/invoices.php`
- Modify: `backend/lang/ru/invoices.php`

**Interfaces:**
- Consumes: Task 11 store and existing shared UI primitives.
- Produces: unmounted routes for list/detail/edit, accessible workflow controls, immutable revision/PDF history, and explicit async/conflict states.

- [x] **Step 1: Write failing component/page tests**

With jsdom and Vue Test Utils, cover URL filters/pagination, create/edit strings, debounced server preview, server totals, publish-before-send, failed save preventing publish/send, version conflict replacing current data and requiring explicit retry, expired/replaced badges, pending-draft restrictions, revision PDF links, mail queued/sent/failed states, same-key retry, duplicate navigation, and conversion navigation from `InvoiceDraftTarget`.

- [x] **Step 2: Implement pages and components**

`QuoteEditPage` never uses `printComputeTotals`, `html2canvas`, `jspdf`, or client PDF upload. It shows the last server preview and marks totals stale while inputs differ. `QuoteDetailPage` renders current effective state and a timeline of every revision with number/label, change reason, hash, publication time, superseded relation, delivery attempts, and immutable PDF view/download links.

- [x] **Step 3: Export but do not mount routes**

`routes.ts` exports children for `finance/quotes`, `finance/quotes/new`, `finance/quotes/:quote`, and `finance/quotes/:quote/edit`. Do not modify `frontend/src/router/index.ts` and do not remove the quote section from `Finance.vue`; the later frontend-cutover plan mounts these records after migration controls pass.

- [x] **Step 4: Run frontend verification**

Run:

```bash
cd frontend
yarn test:js src/modules/finance
yarn typecheck
yarn lint
yarn build
cd ../backend
php artisan test tests/Feature/Guards/TranslationUsageGuardTest.php tests/Feature/Guards/TranslationParityGuardTest.php
```

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add frontend/src/modules/finance backend/lang/de/invoices.php backend/lang/en/invoices.php backend/lang/ru/invoices.php
git commit -m "feat(finance): add quote revision interface"
```

### Task 13: Define legacy migration compatibility and quote cutover gates

**Files:**
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyQuoteMapper.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyQuoteDiagnostic.php`
- Create: `backend/tests/Feature/FinanceModule/Quotes/LegacyQuoteMapperTest.php`
- Create: `docs/finance/quotes-workflow.md`

**Interfaces:**
- Consumes: legacy `finance_quotes`, legacy PDF bytes, foundation `source_type/source_id`, and the new quote commands.
- Produces: a deterministic per-row mapper and explicit gates for the later resumable migration/cutover plans; it does not run a bulk migration.

- [x] **Step 1: Write failing legacy fixture tests**

Cover unnumbered draft, numbered sent, accepted, declined, expired-by-date, soft-deleted, converted invoice/project references, missing PDF, invalid MIME/path, foreign partner/product links, unsupported numeric scale, unknown currency, server-total mismatch, and repeated mapping of the same `(user_id, source_type, source_id)`.

- [x] **Step 2: Implement deterministic mapping**

Map `source_type=legacy.finance_quote` and `source_id={legacy id}`. Unnumbered rows become mutable drafts. Numbered rows become one published revision preserving number, timestamps, snapshot, original PDF path, and verified SHA-256. Conversion IDs become unresolved external references for the later invoice/project migration. Convert legacy decimal strings and JSON numeric tokens without introducing floats; return a blocking diagnostic when exact scale or authoritative totals cannot be preserved.

- [x] **Step 3: Document compatibility and activation gates**

Document schemas, commands, route contracts, error codes, lock order, idempotency semantics, SMTP residual risk, PDF retention, and these later cutover prerequisites:

```text
1. Run the global resumable migration per owner using LegacyQuoteMapper.
2. Require zero blocking diagnostics and exact counts/net/vat/gross by owner/year/currency.
3. Verify every numbered legacy quote has the same number, PDF SHA-256, status,
   partner/product ownership, and conversion reference in the new aggregate.
4. Shadow-read legacy and v2 list/detail responses and compare normalized values.
5. Mount frontend/src/modules/finance/quotes/routes.ts and switch its API base from
   /finance-v2/quotes to the canonical /finance/quotes alias in one cutover commit.
6. Replace legacy quote route registrations; keep rollback routing available while
   new writes are paused, and never dual-write two authoritative quote stores.
7. Remove Finance.vue quote code, finance-store quote methods, FinanceQuoteController,
   legacy QuoteMail, and legacy runtime routes only in finance-legacy-removal.
```

- [x] **Step 4: Run mapper and documentation checks**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule/Quotes/LegacyQuoteMapperTest.php tests/Feature/FinanceQuoteTest.php
vendor/bin/pint --test app/Modules/Finance/Infrastructure/Compatibility tests/Feature/FinanceModule/Quotes
```

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add backend/app/Modules/Finance/Infrastructure/Compatibility backend/tests/Feature/FinanceModule/Quotes/LegacyQuoteMapperTest.php docs/finance/quotes-workflow.md
git commit -m "docs(finance): define quote migration boundary"
```

### Task 14: Run complete verification and record the handoff

**Files:**
- Modify: `docs/finance/quotes-workflow.md`
- Modify: `docs/superpowers/plans/2026-08-28-finance-quotes-workflow.md`

**Interfaces:**
- Consumes: Tasks 1–13.
- Produces: verified quote-module handoff for projects, invoice/payments, migration, frontend cutover, and legacy removal.

- [x] **Step 1: Run focused backend suites**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule tests/Unit/Modules/Finance tests/Feature/FinanceQuoteTest.php tests/Feature/FinanceProjectPlanTest.php tests/Feature/InvoiceEmailTest.php tests/Feature/InvoiceVersionPdfTest.php tests/Feature/Guards/ApiSurfaceGuardTest.php
```

Expected: PASS.

- [x] **Step 2: Run backend quality gates**

Run:

```bash
cd backend
vendor/bin/pint --test app/Modules/Finance tests/Feature/FinanceModule tests/Unit/Modules/Finance
vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G
```

Expected: both commands exit zero.

- [x] **Step 3: Run frontend gates**

Run:

```bash
cd frontend
yarn test:js
yarn typecheck
yarn lint
yarn build
```

Expected: all commands exit zero.

- [x] **Step 4: Run the full backend suite**

Run: `cd backend && FILES_DISK=local php artisan test`

Expected: PASS. If an unrelated pre-existing environment failure remains, record the exact test, environment requirement, and proof that the same failure occurs on this plan's base commit; do not mark this step complete without that evidence.

- [x] **Step 5: Update verification record and completed checkboxes**

Record command, date, test/assertion counts, formatter/static-analysis results, frontend results, and clean status in `docs/finance/quotes-workflow.md`. Mark a checkbox in this plan only after its command has passed.

- [x] **Step 6: Commit**

```bash
git add docs/finance/quotes-workflow.md docs/superpowers/plans/2026-08-28-finance-quotes-workflow.md
git commit -m "docs(finance): verify quote workflow"
```

## Downstream dependencies and non-overlap

- `finance-projects-rewrite` may add a separate `QuoteToProjectPort`; it consumes immutable quote revision snapshots and must not mutate quote revisions.
- `finance-invoices-payments-rewrite` replaces `LegacyInvoiceDraftAdapter` with the modular invoice draft adapter while retaining `QuoteToInvoicePort` and `finance_quote_conversions` idempotency. It owns invoice finalization, numbering, stock movements, payment allocation, dunning, and cancellation.
- `finance-legacy-migration` owns batch orchestration, progress markers, resume semantics, cross-module conversion resolution, control totals, and activation approval. It calls `LegacyQuoteMapper`; it does not duplicate mapping rules.
- `finance-frontend-cutover` mounts the exported quote routes, switches the canonical API alias, removes quote dependencies on the legacy finance snapshot, and verifies browser workflows against migrated data.
- `finance-legacy-removal` removes old quote controller/model/mail/store/view runtime code only after rollback and parity windows close. Historical migrations remain.
