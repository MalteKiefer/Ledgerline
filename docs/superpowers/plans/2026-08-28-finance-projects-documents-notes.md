# Finance Projects, Documents, Notes, and Activity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver an additive, owner-safe Finance project module with explicit project workflows, structured work/time/ledger data, quote and invoice associations through ports, a unified project-document view, and append-only notes and activity history.

**Architecture:** New project authority lives below `App\Modules\Finance` and in new `finance_project_*` tables; it does not reuse the legacy `finance_projects`, `finance_project_tasks`, or `finance_time_entries` tables for writes. Domain objects are framework-independent, Application commands and queries own use cases and transactions through ports, Infrastructure resolves legacy/module document sources without taking ownership of them, HTTP stays thin, and the isolated Vue feature remains unmounted until the migration/cutover plans prove parity. Quote eligibility and invoice creation remain owned by their modules: this plan consumes immutable quote snapshots and emits invoice-draft requests through narrow contracts, without changing quote or invoice workflow files.

**Tech Stack:** PHP 8.5, Laravel 13.8, Eloquent, PostgreSQL production, SQLite tests, PHPUnit 13, Vue 3.5, Pinia 4, Vue Router 5, TypeScript 6, Vitest 4.

**Spec:** `docs/superpowers/specs/2026-08-28-finance-module-rewrite-design.md`

## Global Constraints

- Authoritative money values use integer minor units and hour quantities use scale-4 integers; floating-point arithmetic is forbidden in Domain and Application code.
- Domain code must not import Laravel HTTP, Eloquent, Auth, filesystem, mail, queue, legacy Finance models, or Vue concerns.
- Every project, task, time entry, ledger row, document link, note, activity, operation, and source lookup is owner-scoped; a foreign identifier returns 404.
- Project and task states change only through named commands and workflow objects, never generic CRUD updates.
- Project notes, document notes, and project/document activities are append-only. Corrections create a new entry referencing the prior entry; runtime update/delete endpoints do not exist.
- Files, photos, receipts, bank-transaction receipts, quote revisions, and invoice revisions remain authoritative in their owning modules. Project code stores associations and safe metadata snapshots, never raw bytes, storage paths, SMTP data, or foreign workflow state.
- A quote-to-project conversion consumes one immutable quote revision snapshot and never mutates the quote. Invoice draft creation consumes frozen time-entry data through a port and never numbers, finalizes, sends, cancels, allocates payment, or moves stock.
- New schema, `/api/v1/finance-v2/projects*` routes, and frontend exports are additive. Existing `/api/v1/finance/projects*`, `FinanceProjectPlanController`, `FinanceController`, `frontend/src/stores/finance.ts`, and `Finance.vue` remain active and unchanged until the later cutover/removal plans.
- Mutations return the current resource and optimistic `version`; stale versions return 409 `version_conflict` with the current resource.
- Create-from-quote, invoice-draft creation, attach, and detach operations require `Idempotency-Key`; reuse with different input returns 409 `idempotency_key_reused`.
- No bulk production migration, canonical-route switch, legacy deletion, deployment, push, version bump, or release tag occurs in this plan.

## Locked domain, persistence, and integration decisions

- New aggregate table `finance_project_records` is deliberately separate from legacy `finance_projects`. A project has an owner-unique UUID, optional parent, `business|private` kind, `planned|active|on_hold|done|cancelled` status, optional partner reference, exact budget, optimistic version, archive timestamp, and optional legacy source identity.
- Allowed project transitions are `planned -> active|cancelled`, `active -> on_hold|done|cancelled`, `on_hold -> active|cancelled`, `done -> active`, and `cancelled -> planned`. The last two are explicit reopen commands and are recorded; generic update cannot change status.
- Project archiving is separate from cancellation. Archive hides a project from normal lists but preserves all relationships and history; restore is allowed. There is no v2 force-delete endpoint.
- Legacy free-form `expenses` become structured `finance_project_ledger_entries`; amount is positive minor units and direction is `out|in`. Existing unknown JSON keys are retained in `legacy_metadata` during migration, but new API inputs cannot add arbitrary keys.
- Project summary figures are calculated server-side from active structured ledger entries plus linked payment/receipt sources. Receipt value is skipped when one of its settling bank transactions is also linked, preserving the current no-double-count rule; summaries expose integer minor/scaled values grouped by currency and are never client-authoritative.
- Tasks use `open|in_progress|done`; quote-derived service lines store source revision and source-line index. Hardware lines never create tasks. Time entries preserve scale-4 hours and the rate/currency valid when logged. Once an invoice target is recorded, the time entry cannot be edited, deleted, moved, or unbilled.
- Project and document note types are exactly `note|decision|call|email|meeting|correction`; `correction` requires `supersedes_note_id`, while every other type forbids it. Visibility is `internal|customer`.
- `finance_project_document_links` links a project either to a Finance document series/revision or to an external source reference. Detach sets `detached_at`; it never deletes the link row. Reattaching creates a new row, so association history remains explainable.
- Project document source kinds are exactly `finance_series`, `legacy_invoice`, `file`, `gallery_photo`, `finance_receipt`, `bank_transaction`, and `bank_transaction_receipt`. Roles are exactly `source_quote`, `quote`, `invoice`, `payment`, `receipt`, `file`, `photo`, and `other`.
- Source adapters return metadata only: stable reference, source kind, title/name, MIME, size, SHA-256 when known, document/revision label, occurred timestamp, availability, and an opaque authorized capability route. They do not expose `storage_path`, `blob_path`, `pdf_path`, or raw OCR/search text.
- Link-time metadata is snapshotted so a deleted/unavailable source remains visible as historical evidence. Reads also resolve current metadata; responses contain `snapshot`, optional `current`, and `availability=available|missing|deleted`.
- `customer` note visibility is stored for future exports or a customer portal, but this release exposes notes only to the authenticated owner. There is no public project or note endpoint.
- The project activity page is a cursor-paginated merge of append-only project activities and activities from linked `finance_document_series`; the merge order is `occurred_at DESC, source_kind ASC, source_id DESC`.
- Quote integration is two-sided. This plan produces `ProjectFromQuoteTarget`; the quote workflow must provide an immutable, already-authorized `ProjectQuoteSource` and invoke the target. Project code does not reinterpret quote status, expiry, pending drafts, or supersession. The current quote plan's single `converted` series state is not used as project state.
- Invoice integration is outbound. `ProjectToInvoicePort` receives frozen grouped time lines plus source entry IDs and returns an `InvoiceDraftTarget`; a compatibility adapter may create only a legacy draft. Numbering, totals, finalization, stock, delivery, cancellation, dunning, and payments stay outside this plan.

---

### Task 1: Freeze the legacy project/document boundary and enforce dependency direction

**Files:**
- Modify: `backend/tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php`
- Modify: `backend/tests/Feature/FinanceModule/FinanceModuleBootstrapTest.php`
- Create: `backend/tests/Feature/FinanceModule/Projects/LegacyProjectCompatibilityTest.php`

**Interfaces:**
- Consumes: legacy project, task, time, quote-conversion, attachment, receipt, transaction, and invoice-time routes.
- Produces: a parity inventory and mechanical import rules for the new Projects Domain/Application code.

- [ ] **Step 1: Add failing legacy characterization tests**

Cover current observable behavior: nested project CRUD and cycle rejection; the five legacy statuses; optimistic update conflicts; quote conversion creating tasks only from service lines; frozen hourly rate; task deletion detaching time; invoiced time immutability; invoice-time grouping; JSON ledger semantics; file/photo assignment through owning modules; receipt/transaction project pointers; owner 404s; trash/restore/force behavior; and the 500/1000 row caps.

- [ ] **Step 2: Record the known gaps as explicit assertions**

Demonstrate that project status is currently writable through generic update, project money/time uses floats, manual ledger rows replace one JSON array, project notes are one mutable text field, document links have no append-only history, and the project detail is assembled from the global `/finance/data` snapshot. These assertions document why v2 remains shadow-only.

- [ ] **Step 3: Extend the module source guard**

In `FinanceModuleBootstrapTest`, scan `Domain/Projects` and `Application/{Commands,Queries,DTOs,Ports,Services}/Projects` and reject:

```php
$forbidden = [
    'App\\Http\\Controllers',
    'App\\Models\\FinanceProject',
    'App\\Models\\FinanceProjectTask',
    'App\\Models\\FinanceTimeEntry',
    'App\\Models\\FinanceQuote',
    'App\\Models\\Invoice',
    'App\\Models\\FileEntry',
    'App\\Models\\GalleryPhoto',
    'Illuminate\\Http',
    'Illuminate\\Database\\Eloquent',
    'Illuminate\\Support\\Facades',
];
```

Allow legacy and cross-module models only below `Infrastructure/Compatibility` and `Infrastructure/Integrations`.

- [ ] **Step 4: Run the legacy baseline**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php tests/Feature/FinanceModule/Projects/LegacyProjectCompatibilityTest.php tests/Feature/FinanceProjectPlanTest.php tests/Feature/FinanceRelationalTest.php
```

Expected: existing behavior assertions pass and gap assertions reproduce the documented v1 limitations.

- [ ] **Step 5: Commit**

```bash
git add backend/tests/Feature/FinanceModule
git commit -m "test(finance): freeze legacy project compatibility"
```

### Task 2: Define project, work-item, and exact-value domain rules

**Files:**
- Create: `backend/app/Modules/Finance/Domain/Projects/ProjectStatus.php`
- Create: `backend/app/Modules/Finance/Domain/Projects/ProjectWorkflow.php`
- Create: `backend/app/Modules/Finance/Domain/Projects/ProjectKind.php`
- Create: `backend/app/Modules/Finance/Domain/Projects/WorkItemStatus.php`
- Create: `backend/app/Modules/Finance/Domain/Projects/WorkItemWorkflow.php`
- Create: `backend/app/Modules/Finance/Domain/Projects/ProjectBudget.php`
- Create: `backend/app/Modules/Finance/Domain/Projects/TimeCharge.php`
- Create: `backend/app/Modules/Finance/Domain/Projects/Exception/InvalidProjectAction.php`
- Create: `backend/tests/Unit/Modules/Finance/Domain/Projects/ProjectWorkflowTest.php`
- Create: `backend/tests/Unit/Modules/Finance/Domain/Projects/TimeChargeTest.php`

**Interfaces:**
- Consumes: shared `StateMachine`, `Money`, `DecimalQuantity`, and `Rounding`.
- Produces: exact status transitions and integer-only time-value calculation.

- [ ] **Step 1: Write failing project workflow tests**

Assert every allowed transition listed under locked decisions, reject self/unknown/direct terminal jumps, and require explicit reopen actions. Stable errors are `invalid_transition`, `project_archived`, `project_parent_cycle`, and `project_parent_archived`.

```php
$workflow->assertCan(ProjectStatus::Active, ProjectStatus::Done);
$this->assertFalse($workflow->can(ProjectStatus::Planned, ProjectStatus::Done));
```

- [ ] **Step 2: Write failing work-item tests**

Allow `open -> in_progress|done`, `in_progress -> open|done`, and `done -> in_progress`; reject self/unknown transitions. A milestone accepts no estimated quantity and a quote-derived task requires a non-negative, zero-based source-line index.

- [ ] **Step 3: Write failing exact time-charge tests**

`TimeCharge::calculate(DecimalQuantity $hours, Money $hourlyRate): Money` uses `hours->scaled() * rate_minor / 10000` and `Rounding::halfAwayFromZero`. Cover `2.5000 × 100.00 = 250.00`, `0.3333 × 120.00 = 40.00`, negative correction hours, overflow, and currency preservation.

- [ ] **Step 4: Implement enum/value objects and workflows**

```php
final readonly class TimeCharge
{
    public static function calculate(DecimalQuantity $hours, Money $hourlyRate): Money;
}

final class InvalidProjectAction extends DomainException
{
    public function __construct(public readonly string $errorCode) {}
}
```

No class in this task reads configuration, dates, authenticated users, or persistence.

- [ ] **Step 5: Run unit tests and formatter**

Run:

```bash
cd backend
php artisan test tests/Unit/Modules/Finance/Domain/Projects
vendor/bin/pint --test app/Modules/Finance/Domain/Projects tests/Unit/Modules/Finance/Domain/Projects
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance/Domain/Projects backend/tests/Unit/Modules/Finance/Domain/Projects
git commit -m "feat(finance): define project domain rules"
```

### Task 3: Add the isolated project, work, document-link, note, activity, and operation schema

**Files:**
- Create: `backend/database/migrations/2027_03_04_100000_create_finance_project_workflow.php`
- Create: `backend/tests/Feature/FinanceModule/Projects/ProjectSchemaTest.php`

**Interfaces:**
- Consumes: `users`, foundation document-core tables, and PostgreSQL/SQLite-compatible schema APIs.
- Produces: all additive `finance_project_*` persistence used by later tasks.

- [ ] **Step 1: Write failing table, column, index, and constraint tests**

Assert owner-composite foreign keys, UUID/source uniqueness, allowed enum/check values, positive minor-unit constraints, nonzero hours, version defaults, source-revision/line pairing, note correction references, document source pairing, and safe delete rules. Prove cross-owner parent, task, time, note, activity, document-series, and revision references fail at the database boundary.

- [ ] **Step 2: Create the aggregate and work tables**

```text
finance_project_records:
  id, user_id, uuid, parent_project_id nullable, source_type/source_id nullable,
  name, kind(business|private), status(planned|active|on_hold|done|cancelled),
  partner_reference nullable, starts_on nullable, due_on nullable,
  budget_minor nullable, currency char(3), version unsigned default 0,
  archived_at nullable, created_by nullable, created_at, updated_at

finance_project_work_items:
  id, user_id, project_id, uuid, title, description nullable,
  status(open|in_progress|done), starts_on nullable, due_on nullable,
  estimate_quantity_scaled nullable, is_milestone, sort,
  source_revision_id/source_line_index nullable, product_reference nullable,
  version unsigned default 0, created_by nullable, deleted_at, created_at, updated_at

finance_project_time_entries:
  id, user_id, project_id, work_item_id nullable, uuid, worked_on,
  quantity_scaled, description nullable, billable,
  hourly_rate_minor nullable, currency char(3),
  invoice_target_reference nullable, invoiced_at nullable,
  version unsigned default 0, created_by nullable, deleted_at, created_at, updated_at

finance_project_ledger_entries:
  id, user_id, project_id, uuid, direction(out|in), amount_minor,
  currency char(3), occurred_on nullable, title nullable, note nullable,
  category_reference nullable, payment_method_reference nullable,
  legacy_metadata json nullable, version unsigned default 0,
  created_by nullable, deleted_at, created_at, updated_at
```

Use composite `(user_id,id)` uniqueness and composite owner/project foreign keys. Parent references use `NO ACTION` so an archive never reparents children silently. A time-entry task must belong to the same owner/project. An invoiced target is set together with `invoiced_at`.

- [ ] **Step 3: Create document-link and history tables**

```text
finance_project_document_links:
  id, user_id, project_id, source_type, source_reference,
  document_series_id nullable, pinned_revision_id nullable, role,
  metadata_snapshot json, attached_by nullable, attached_at,
  detached_by nullable, detached_at nullable

finance_project_notes:
  id, user_id, project_id, type, visibility(internal|customer), body,
  supersedes_note_id nullable, created_by nullable, created_at

finance_project_activities:
  id, user_id, project_id, type, subject_type nullable,
  subject_reference nullable, payload json nullable,
  created_by nullable, occurred_at, created_at

finance_project_operations:
  id, user_id, project_id nullable, operation, idempotency_key,
  request_sha256, state(reserved|running|succeeded|failed),
  result json nullable, error_code nullable, started_at, completed_at nullable
```

Add unique `(user_id, operation, idempotency_key)`, owner/timestamp indexes, project/type/time indexes, source lookup indexes, and project-note search indexes. PostgreSQL uses a partial unique index for one active `(user_id, project_id, source_type, source_reference, role)` link; SQLite tests use insert/update triggers with the same rule.

Extend `finance_document_notes` additively with nullable `supersedes_note_id`. Its composite foreign key requires the referenced note to belong to the same owner and document series and rejects self-reference; existing note rows remain valid with null.

- [ ] **Step 4: Add exact integrity checks**

Require both-or-neither pairs for project source identity, quote line identity, and invoice target/timestamp. `detached_by` requires `detached_at`, while a system/migration detach may have a timestamp and null actor. A `finance_series` link requires `document_series_id` and may pin a revision; external source kinds forbid both columns. A pinned revision must belong to the linked owner/series, and `source_reference` must equal the owned series UUID. Notes may supersede only another note in the same owner/project, cannot self-reference, and enforce the locked type/correction rule.

- [ ] **Step 5: Run migration and schema tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/Projects/ProjectSchemaTest.php
php artisan migrate:fresh --env=testing --force
```

Expected: PASS on SQLite; PostgreSQL-specific DDL strings are asserted exactly.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2027_03_04_100000_create_finance_project_workflow.php backend/tests/Feature/FinanceModule/Projects/ProjectSchemaTest.php
git commit -m "feat(finance): add isolated project workflow schema"
```

### Task 4: Add focused records, DTOs, repositories, pagination, and idempotency

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectId.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectView.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectPage.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectListFilter.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/CreateProjectData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/UpdateProjectData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ChangeProjectStatusData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/MoveProjectData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/WorkItemView.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/TimeEntryView.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/LedgerEntryView.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectDocumentSourceRef.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/OperationReservation.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectRepository.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectOperationRepository.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectReferenceResolver.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Projects/GetProject.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Projects/ListProjects.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/ProjectRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/ProjectWorkItemRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/ProjectTimeEntryRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/ProjectLedgerEntryRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/ProjectDocumentLinkRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/ProjectNoteRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/ProjectActivityRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/ProjectOperationRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentProjectRepository.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentProjectOperationRepository.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyProjectReferenceResolver.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/tests/Feature/FinanceModule/Projects/ProjectPersistenceTest.php`

**Interfaces:**
- Consumes: Task 3 schema and legacy partner/payment/category existence only through Infrastructure.
- Produces: owner-scoped aggregate access, stable pages, compare-and-swap writes, reference validation, and operation replay.

- [ ] **Step 1: Write failing owner, filter, and pagination tests**

Cover foreign UUID/child IDs returning no result; filters `q`, `status`, `kind`, `partner_reference`, `parent_id`, `archived`, start/due ranges; allowed sorts `updated_at`, `name`, `starts_on`, `due_on`, `status`; page size `1..100`; and stable tie-breaking by ID. `q` matches project name and append-only note body without leaking another owner.

- [ ] **Step 2: Write failing optimistic and idempotency tests**

```php
interface ProjectOperationRepository
{
    public function reserve(
        int $ownerId,
        string $operation,
        string $key,
        string $requestSha256,
        ?ProjectId $projectId,
    ): OperationReservation;

    public function succeed(OperationReservation $reservation, array $result): void;
    public function fail(OperationReservation $reservation, string $errorCode): void;
}
```

Assert new, in-progress, completed replay, and `idempotency_key_reused` outcomes under unique-key contention. Compare-and-swap failure returns a current `ProjectView`.

- [ ] **Step 3: Define repository contracts**

```php
interface ProjectRepository
{
    public function get(ProjectId $id): ProjectView;
    public function page(ProjectListFilter $filter): ProjectPage;
    public function create(CreateProjectData $data): ProjectView;
    public function update(UpdateProjectData $data): ProjectView;
    public function changeStatus(ChangeProjectStatusData $data): ProjectView;
    public function move(MoveProjectData $data): ProjectView;
    public function archive(ProjectId $id, int $expectedVersion): ProjectView;
    public function restore(ProjectId $id, int $expectedVersion): ProjectView;
}
```

`ProjectReferenceResolver` exposes `assertOwnedPartnerReference`, `assertOwnedPaymentMethodReference`, and `assertOwnedCategoryReference`; references are opaque strings such as `legacy-partner:17`, never caller-trusted numeric IDs.

`ProjectId` carries both `ownerId` and the public project UUID. `ProjectListFilter` carries the owner explicitly; repository methods never infer owner from a global authenticated-user facade. Add `assertOwnedProductReference` to `ProjectReferenceResolver` for quote-derived work items.

- [ ] **Step 4: Implement records, adapters, and lock order**

Records expose typed relations and keep owner, UUID, source identity, invoice target, audit actor, operation result, and link snapshot out of `$fillable`. Repository transactions lock project, parent/child when applicable, then child rows, then operation row. Use DB constraints as the final owner guard rather than application-only existence checks.

- [ ] **Step 5: Bind ports and run checks**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/Projects/ProjectPersistenceTest.php
vendor/bin/pint --test app/Modules/Finance/Application/DTOs/Projects app/Modules/Finance/Application/Ports/Projects app/Modules/Finance/Infrastructure/Persistence tests/Feature/FinanceModule/Projects
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance backend/tests/Feature/FinanceModule/Projects/ProjectPersistenceTest.php
git commit -m "feat(finance): persist owner scoped project aggregates"
```

### Task 5: Implement project creation, editing, hierarchy, workflow, archive, and queries

**Files:**
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/CreateProject.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/UpdateProject.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/ChangeProjectStatus.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/MoveProject.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/ArchiveProject.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/RestoreProject.php`
- Create: `backend/tests/Feature/FinanceModule/Projects/ProjectApplicationTest.php`

**Interfaces:**
- Consumes: Task 2 workflows and Task 4 repository/reference ports.
- Produces: named project lifecycle commands returning `ProjectView`.

- [ ] **Step 1: Write failing creation/update tests**

Require nonblank name, valid kind, ISO dates, `starts_on <= due_on`, uppercase currency, integer budget minor units, owned partner reference, and optional owned non-archived parent. Assert create adds `project.created`; update cannot change status, parent, owner, UUID, source, archive state, or history.

- [ ] **Step 2: Write failing status and hierarchy tests**

Test every transition, reopen, version conflict, archived refusal, foreign/archived parent, self-parent, deep cycle, concurrent opposite moves, and deterministic lock ordering by ascending project ID. Status/move commands append `project.status_changed` and `project.moved` with old/new values.

- [ ] **Step 3: Write failing archive/restore tests**

Archive preserves children, tasks, time, ledger, notes, activities, and links; normal reads omit it. Restore returns the same UUID and relationships. A child whose parent is archived surfaces with `parent_available=false`; no row is silently reparented.

- [ ] **Step 4: Implement the commands**

```php
final readonly class ChangeProjectStatusData
{
    public function __construct(
        public ProjectId $projectId,
        public int $expectedVersion,
        public ProjectStatus $target,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
```

Every command performs one use case, appends its activity inside the same transaction, and returns the refreshed DTO.

- [ ] **Step 5: Run focused tests**

Run: `cd backend && php artisan test tests/Feature/FinanceModule/Projects/ProjectApplicationTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance/Application backend/tests/Feature/FinanceModule/Projects/ProjectApplicationTest.php
git commit -m "feat(finance): add project lifecycle commands"
```

### Task 6: Implement work items, exact time tracking, structured ledger entries, and invoice-draft port

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/CreateWorkItemData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/UpdateWorkItemData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/LogTimeData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/UpdateTimeData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/CreateLedgerEntryData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/InvoiceTimeData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/InvoiceTimeLine.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/InvoiceDraftTarget.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectTotalsView.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectFinancialSourceRow.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectWorkRepository.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectToInvoicePort.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectRateSource.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectFinancialSource.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/CreateWorkItem.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/UpdateWorkItem.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/DeleteWorkItem.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/ReorderWorkItems.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/LogProjectTime.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/UpdateProjectTime.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/DeleteProjectTime.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/CreateLedgerEntry.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/UpdateLedgerEntry.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/DeleteLedgerEntry.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/CreateInvoiceDraftFromTime.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Projects/ListProjectWork.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Projects/ListProjectLedger.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Projects/GetProjectTotals.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentProjectWorkRepository.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyProjectRateSource.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyProjectFinancialSource.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyInvoiceDraftFromTimeAdapter.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/tests/Feature/FinanceModule/Projects/ProjectWorkApplicationTest.php`

**Interfaces:**
- Consumes: project aggregate, exact project domain values, owner reference ports, and an invoice-draft target port.
- Produces: paginated tasks/time/ledger and immutable billing stamps without invoice workflow knowledge.

- [ ] **Step 1: Write failing work-item and paging tests**

Cover task ownership, task/project matching, status transitions, milestones, scale-4 estimates, stable ordering, reorder requiring exactly the current live task IDs, optimistic conflicts, soft deletion detaching uninvoiced time from the task, and pages of at most 100.

- [ ] **Step 2: Write failing time tests**

Accept decimal strings only, reject JSON floats and zero, preserve signed correction hours, freeze explicit or port-supplied hourly rate/currency, calculate unbilled values with `TimeCharge`, and reject any edit/delete/move after `invoice_target_reference` is set. Aggregates return integer `hours_scaled` and `value_minor`, never floats.

```php
interface ProjectRateSource
{
    public function frozenRate(
        int $ownerId,
        ?string $partnerReference,
        string $currency,
    ): ?Money;
}
```

When the client omits a rate and the port returns null, reject the write with `hourly_rate_required`; never substitute a process-wide default inside Domain code.

- [ ] **Step 3: Write failing ledger tests**

Require positive integer minor units, direction, currency, owned optional references, and page filters for direction/date/category. Create/update/delete each append a distinct activity; soft-deleted rows remain in audit history but not active totals. `GetProjectTotals` groups exact ledger and linked bank/receipt values by currency and proves the settling-transaction de-duplication rule with integer assertions.

```php
interface ProjectFinancialSource
{
    /** @return list<ProjectFinancialSourceRow> */
    public function rows(int $ownerId, ProjectId $projectId): array;
}
```

Each row carries source reference, signed minor units, currency, occurred date, and settling transaction references. `LegacyProjectFinancialSource` reads only actively linked owner-scoped bank transactions/receipts and never exposes their mutable models to Application code.

- [ ] **Step 4: Define and test the invoice boundary**

```php
interface ProjectToInvoicePort
{
    /** @param list<InvoiceTimeLine> $lines @param list<string> $timeEntryUuids */
    public function createDraft(
        int $ownerId,
        ProjectView $project,
        array $lines,
        array $timeEntryUuids,
        string $idempotencyKey,
    ): InvoiceDraftTarget;
}
```

`InvoiceDraftTarget` contains `targetReference`, a `ProjectDocumentSourceRef`, and an optional navigation capability. Group selected billable entries by `(hourly_rate_minor,currency)` using integer sums, reserve the operation, call the port once, then atomically stamp every still-unbilled entry and attach the returned document source with role `invoice`. Persist the target/source checkpoint before the local stamp so a retry resumes without a second invoice. Concurrency permits one target; retry returns it. The compatibility adapter returns `source_type=legacy_invoice`, creates only a legacy `draft` invoice, and copies lines/customer references; it does not number, finalize, calculate authoritative invoice totals, move stock, send, dun, cancel, or allocate payments.

- [ ] **Step 5: Run focused and legacy parity tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/Projects/ProjectWorkApplicationTest.php tests/Feature/FinanceProjectPlanTest.php tests/Unit/Modules/Finance/Domain/Projects
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance backend/tests/Feature/FinanceModule/Projects/ProjectWorkApplicationTest.php
git commit -m "feat(finance): add exact project work and billing boundary"
```

### Task 7: Implement the immutable quote-to-project target without changing quote workflow files

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectQuoteSource.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectTarget.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/CreateProjectFromQuoteData.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectFromQuoteTarget.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/CreateProjectFromQuote.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Integrations/Quotes/FinanceQuoteProjectTarget.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/tests/Feature/FinanceModule/Projects/QuoteProjectTargetTest.php`

**Interfaces:**
- Consumes: an owner-authorized immutable quote revision snapshot supplied by the quote workflow.
- Produces: one project target per owner/source revision, service-derived tasks, and no quote mutation.

- [ ] **Step 1: Write failing contract tests with a quote-side fake**

```php
interface ProjectFromQuoteTarget
{
    public function create(
        int $ownerId,
        ProjectQuoteSource $source,
        string $idempotencyKey,
    ): ProjectTarget;
}
```

`ProjectQuoteSource` contains series UUID, revision ID/hash, number/label, title, partner reference, issue/valid dates, currency, authoritative totals, and canonical immutable lines. It contains no quote status mutation method.

- [ ] **Step 2: Test mapping and idempotency**

Assert the project name/title, partner, budget net minor units, source-document link pinned to the exact revision, and one task per nonblank `kind=service` line. The line quantity becomes `estimate_quantity_scaled`; description first line becomes title, remaining text becomes description; hardware creates no task. Same revision under concurrent keys returns one project; different accepted revisions may intentionally create different projects only when the quote side calls with different source revision IDs.

- [ ] **Step 3: Test owner and snapshot integrity**

Reject owner mismatch, unknown document series/revision, hash mismatch, a revision not belonging to the series, JSON floats, invalid line scale, and foreign product/partner references. The project module validates structural integrity and ownership, but does not decide accepted/expired/replaced/current/pending-draft eligibility; that decision remains a quote-side precondition.

- [ ] **Step 4: Implement the target adapter and activity set**

Create project, pinned `source_quote` link, tasks, `project.created_from_quote`, `project.document_attached`, and task-created activities in one transaction. Do not update `finance_quote_series`, `finance_quote_conversions`, `finance_document_revisions`, or quote activities.

- [ ] **Step 5: Run project and foundation revision tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/Projects/QuoteProjectTargetTest.php tests/Feature/FinanceModule/DocumentRevisionApplicationTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance/Application/DTOs/Projects backend/app/Modules/Finance/Application/Ports/Projects backend/app/Modules/Finance/Application/Commands/Projects backend/app/Modules/Finance/Infrastructure/Integrations/Quotes backend/app/Modules/Finance/FinanceServiceProvider.php backend/tests/Feature/FinanceModule/Projects/QuoteProjectTargetTest.php
git commit -m "feat(finance): add quote to project target port"
```

### Task 8: Build the owner-validated document source catalog and historical associations

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectDocumentMetadata.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectDocumentView.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectDocumentPage.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectDocumentFilter.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectDocumentSourcePage.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectDocumentSourceFilter.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectDocumentSource.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectDocumentCatalog.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectDocumentRepository.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/AttachProjectDocument.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/DetachProjectDocument.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Projects/ListProjectDocuments.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Projects/SearchProjectDocumentSources.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Documents/CompositeProjectDocumentCatalog.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Documents/FinanceSeriesDocumentSource.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Documents/LegacyInvoiceDocumentSource.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Documents/LegacyFileDocumentSource.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Documents/LegacyGalleryPhotoDocumentSource.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Documents/LegacyFinanceReceiptDocumentSource.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Documents/LegacyBankTransactionDocumentSource.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Documents/LegacyBankReceiptDocumentSource.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentProjectDocumentRepository.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/tests/Feature/FinanceModule/Projects/ProjectDocumentsTest.php`

**Interfaces:**
- Consumes: owner-scoped source modules and foundation series/revisions through read-only Infrastructure adapters.
- Produces: safe source search, idempotent historical attach/detach, and a unified filtered page.

- [ ] **Step 1: Write failing catalog contract tests**

```php
interface ProjectDocumentSource
{
    public function supports(string $sourceType): bool;
    public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata;
    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage;
}
```

For every source kind assert owner isolation, missing/deleted state, metadata mapping, source-specific authorized capability route, and absence of raw paths/OCR/search text. Finance series metadata pins a requested revision; invoice/quote workflow fields are read-only. `legacy_invoice` exists only for the temporary invoice-draft adapter and migration window and is replaced by `finance_series` when invoice migration resolves the target.

- [ ] **Step 2: Write failing attach/detach tests**

Attach validates project/source owner, role/source compatibility, optional pinned revision, snapshots canonical metadata, appends activity, and returns current link. Same idempotency key replays; a second active duplicate is rejected with `document_already_attached`. Detach changes only the link's detach fields, appends activity, and never changes/deletes the source. Reattach creates a new link row.

- [ ] **Step 3: Write failing unified query tests**

For attached documents, cover filters `q`, `source_type`, `role`, `mime_group`, `availability`, date range, `active|detached|all`, and `page/per_page`. Sort `attached_at DESC, id DESC`; use stored snapshot when the source is deleted and current metadata when available. Source search accepts `q`, source types, MIME group, date bounds, `cursor`, and `per_page=1..100`, then sorts by normalized source occurrence time, source type, and reference. Ensure neither query loads the full Files/Gallery/Finance snapshots.

- [ ] **Step 4: Implement adapters and bindings**

`CompositeProjectDocumentCatalog` rejects zero or multiple adapters claiming a source type. Source searches run separately with bounded per-source limits, then merge by cursor before returning at most 100 items. Capability values are route identifiers plus opaque parameters; HTTP Resources create URLs.

- [ ] **Step 5: Run source, attachment, and legacy source tests**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule/Projects/ProjectDocumentsTest.php tests/Feature/FinanceRelationalTest.php tests/Feature/FilesRelationalTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance backend/tests/Feature/FinanceModule/Projects/ProjectDocumentsTest.php
git commit -m "feat(finance): add unified project document associations"
```

### Task 9: Make project and document notes and activities append-only and queryable

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/AppendProjectNoteData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/AppendDocumentNoteData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/HistoryItemView.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/HistoryPage.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/Projects/ProjectNoteFilter.php`
- Create: `backend/app/Modules/Finance/Application/Ports/Projects/ProjectHistoryRepository.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/AppendProjectNote.php`
- Create: `backend/app/Modules/Finance/Application/Commands/Projects/AppendDocumentNote.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Projects/ListProjectNotes.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Projects/ListDocumentNotes.php`
- Create: `backend/app/Modules/Finance/Application/Queries/Projects/ListProjectActivity.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Exception/AppendOnlyRecordMutation.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentProjectHistoryRepository.php`
- Modify: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/ProjectNoteRecord.php`
- Modify: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/ProjectActivityRecord.php`
- Modify: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/DocumentNoteRecord.php`
- Modify: `backend/app/Modules/Finance/Infrastructure/Persistence/Exception/PublishedRevisionMutation.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Modify: `backend/tests/Feature/FinanceModule/DocumentPersistenceTest.php`
- Create: `backend/tests/Feature/FinanceModule/Projects/ProjectHistoryTest.php`

**Interfaces:**
- Consumes: project/history tables, foundation document notes/activities, and linked series.
- Produces: immutable note creation and cursor-paginated project/document history.

- [ ] **Step 1: Write failing append-only guard tests**

Assert normal, quiet, bulk, upsert, update-or-insert, delete, and truncate paths cannot mutate project notes/activities or foundation document notes/activities. Inserts through explicit repositories remain allowed. Owner-wide database cascades remain possible. New project records throw `AppendOnlyRecordMutation` with stable kind `project_note` or `project_activity`; existing document activities keep their `PublishedRevisionMutation::activity()` contract, and document notes use the new `PublishedRevisionMutation::note()` factory so foundation callers retain the same exception type.

- [ ] **Step 2: Write failing note command tests**

Require body `1..100000`, allowed visibility, allowed type, actor, and owner-scoped project/series/revision. A correction supplies `supersedes_note_id` in the same aggregate; the earlier body/visibility remains unchanged. Appending a project note also appends `project.note_added`; appending a document note uses foundation series ownership and does not fabricate a project activity unless the series is actively linked.

- [ ] **Step 3: Write failing history query tests**

Project notes filter by `q`, type, visibility, author, and date with page size `1..100`. Project activity merges project activities with activities from active or historically linked document series, de-duplicates by `(source_kind,source_id)`, scrubs secret/raw-error keys, and cursor-paginates with the locked ordering. Foreign linked-series activity is impossible through composite owner joins.

- [ ] **Step 4: Implement repository and record guards**

```php
interface ProjectHistoryRepository
{
    public function appendProjectNote(AppendProjectNoteData $data): HistoryItemView;
    public function appendDocumentNote(AppendDocumentNoteData $data): HistoryItemView;
    public function projectNotes(ProjectId $projectId, ProjectNoteFilter $filter): HistoryPage;
    public function documentNotes(int $ownerId, string $seriesUuid, ProjectNoteFilter $filter): HistoryPage;
    public function projectActivity(ProjectId $projectId, ?string $cursor, int $perPage): HistoryPage;
}
```

Store only allowlisted activity payload keys; recipient addresses, raw exception text, SMTP settings, storage paths, OCR, and document bodies are forbidden.

- [ ] **Step 5: Run history and foundation immutability tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/Projects/ProjectHistoryTest.php tests/Feature/FinanceModule/DocumentPersistenceTest.php tests/Feature/FinanceModule/DocumentCoreSchemaTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance backend/tests/Feature/FinanceModule/DocumentPersistenceTest.php backend/tests/Feature/FinanceModule/Projects/ProjectHistoryTest.php
git commit -m "feat(finance): add append only project and document history"
```

### Task 10: Expose thin v2 HTTP controllers, stable Resources, filters, and OpenAPI

**Files:**
- Create: `backend/app/Modules/Finance/Http/Requests/Projects/ProjectListRequest.php`
- Create: `backend/app/Modules/Finance/Http/Requests/Projects/ProjectWriteRequest.php`
- Create: `backend/app/Modules/Finance/Http/Requests/Projects/ProjectActionRequest.php`
- Create: `backend/app/Modules/Finance/Http/Requests/Projects/ProjectWorkRequest.php`
- Create: `backend/app/Modules/Finance/Http/Requests/Projects/ProjectDocumentRequest.php`
- Create: `backend/app/Modules/Finance/Http/Requests/Projects/ProjectNoteRequest.php`
- Create: `backend/app/Modules/Finance/Http/Resources/Projects/ProjectResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/Projects/ProjectPageResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/Projects/ProjectWorkResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/Projects/ProjectTotalsResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/Projects/ProjectDocumentResource.php`
- Create: `backend/app/Modules/Finance/Http/Resources/Projects/ProjectHistoryResource.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectStatusController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectMoveController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectArchiveController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectWorkController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectTotalsController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectTimeInvoiceController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectLedgerController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectDocumentController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectNoteController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Projects/ProjectActivityController.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/Documents/DocumentNoteController.php`
- Modify: `backend/app/Modules/Finance/Http/Routes/api.php`
- Modify: `openapi.yaml`
- Create: `backend/tests/Feature/FinanceModule/Projects/ProjectApiTest.php`

**Interfaces:**
- Consumes: Tasks 5–9 commands/queries.
- Produces: additive v2 project, document, note, work, ledger, and history APIs.

- [ ] **Step 1: Write failing request/resource/API tests**

Cover authentication/module/throttle gates, UUID route keys, owner 404s, validation codes, 409 version/idempotency conflicts, pagination/meta/cursors, filter query preservation, integer minor/scaled values, append-only absence of note/activity update/delete routes, and no owner IDs, raw paths, OCR, secrets, or legacy numeric IDs in Resources.

- [ ] **Step 2: Register the exact route surface**

```text
GET    /finance-v2/projects
POST   /finance-v2/projects
GET    /finance-v2/projects/{project}
PUT    /finance-v2/projects/{project}
POST   /finance-v2/projects/{project}/status
POST   /finance-v2/projects/{project}/move
DELETE /finance-v2/projects/{project}
POST   /finance-v2/projects/{project}/restore
GET    /finance-v2/projects/{project}/work-items
POST   /finance-v2/projects/{project}/work-items
PUT    /finance-v2/projects/{project}/work-items/{workItem}
DELETE /finance-v2/projects/{project}/work-items/{workItem}
POST   /finance-v2/projects/{project}/work-items/reorder
GET    /finance-v2/projects/{project}/time-entries
POST   /finance-v2/projects/{project}/time-entries
PUT    /finance-v2/projects/{project}/time-entries/{entry}
DELETE /finance-v2/projects/{project}/time-entries/{entry}
POST   /finance-v2/projects/{project}/invoice-drafts
GET    /finance-v2/projects/{project}/totals
GET    /finance-v2/projects/{project}/ledger
POST   /finance-v2/projects/{project}/ledger
PUT    /finance-v2/projects/{project}/ledger/{entry}
DELETE /finance-v2/projects/{project}/ledger/{entry}
GET    /finance-v2/projects/{project}/documents
POST   /finance-v2/projects/{project}/documents
DELETE /finance-v2/projects/{project}/documents/{link}
GET    /finance-v2/projects/{project}/document-sources
GET    /finance-v2/projects/{project}/notes
POST   /finance-v2/projects/{project}/notes
GET    /finance-v2/projects/{project}/activities
GET    /finance-v2/document-series/{series}/notes
POST   /finance-v2/document-series/{series}/notes
```

Name them under `api.finance-v2.projects.*` and `api.finance-v2.document-series.notes.*`. Nested child resolution always starts from the owned project/series, not the child ID alone.

- [ ] **Step 3: Implement thin controllers and stable error mapping**

Each controller validates/authorizes, constructs one DTO, calls one command/query, and returns one Resource. Map `invalid_transition`, `project_archived`, `time_invoiced`, `document_already_attached`, `document_not_attached`, `idempotency_key_reused`, and `version_conflict` consistently to 409 or 422; ownership failures are 404.

- [ ] **Step 4: Add parallel OpenAPI components**

Keep every legacy project path/schema. Add `FinanceV2Project`, `FinanceV2ProjectPage`, `FinanceV2WorkItem`, `FinanceV2TimeEntry`, `FinanceV2LedgerEntry`, `FinanceV2ProjectTotals`, `FinanceV2ProjectDocument`, `FinanceV2HistoryItem`, request schemas, exact enum/error responses, UUID IDs, integer minor/scaled formats, `Idempotency-Key`, page metadata, and activity/source-search cursors. State that capability URLs are authorized API URLs and metadata paths are never returned.

- [ ] **Step 5: Run API and surface guards**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule/Projects/ProjectApiTest.php tests/Feature/Guards/ApiSurfaceGuardTest.php tests/Feature/FinanceModule
```

Expected: PASS and every route appears in `openapi.yaml`.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance/Http openapi.yaml backend/tests/Feature/FinanceModule/Projects/ProjectApiTest.php
git commit -m "feat(finance): expose modular project API"
```

### Task 11: Build isolated frontend models, API, stores, URL filters, and detail loaders

**Files:**
- Create: `frontend/src/modules/finance/models/project.ts`
- Create: `frontend/src/modules/finance/models/projectDocument.ts`
- Create: `frontend/src/modules/finance/models/history.ts`
- Create: `frontend/src/modules/finance/api/projectApi.ts`
- Create: `frontend/src/modules/finance/stores/projects.ts`
- Create: `frontend/src/modules/finance/composables/useProjectFilters.ts`
- Create: `frontend/src/modules/finance/composables/useProjectDetail.ts`
- Create: `frontend/src/modules/finance/api/__tests__/projectApi.test.ts`
- Create: `frontend/src/modules/finance/stores/__tests__/projects.test.ts`
- Create: `frontend/src/modules/finance/composables/__tests__/useProjectFilters.test.ts`
- Create: `frontend/src/modules/finance/composables/__tests__/useProjectDetail.test.ts`

**Interfaces:**
- Consumes: Task 10 wire contract and the shared API client's existing conflict support.
- Produces: typed paginated project state with URL-owned filters and independently paged detail panels.

- [ ] **Step 1: Write failing wire-model/API tests**

Assert UUIDs stay strings, money/hours remain integer minor/scaled values, no `number` conversion occurs, source references remain opaque, and methods target only `/api/v1/finance-v2`. Test `Idempotency-Key` propagation and reuse across retries for attach/detach and invoice-draft actions.

- [ ] **Step 2: Write failing list-store/filter tests**

Round-trip `q`, `status`, `kind`, `parent_id`, `partner_reference`, `archived`, date bounds, `sort`, `direction`, and `page` through `route.query`; changing a filter resets page to 1. Test request cancellation, stale-response suppression, current-resource upsert, conflict replacement, and no global Finance snapshot load.

- [ ] **Step 3: Write failing detail-loader tests**

Load project, server-calculated totals, work items, time, ledger, documents, notes, and activity independently. A panel failure leaves other panels usable and visibly failed; opening another project cancels every old request. Each panel keeps its own filter/page/cursor and refreshes only after relevant mutations.

- [ ] **Step 4: Implement the focused client layer**

Expose list/show/create/update/status/move/archive/restore; work/time/ledger CRUD; invoice draft; document source search/attach/detach; append note; list notes/activity/document notes. Generate action keys with `crypto.randomUUID()` at the user-action boundary.

- [ ] **Step 5: Run frontend unit gates**

Run:

```bash
cd frontend
yarn test:js src/modules/finance
yarn typecheck
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/modules/finance
git commit -m "feat(finance): add modular project client state"
```

### Task 12: Build project pages, work/ledger panels, document picker, notes, and activity timeline

**Files:**
- Create: `frontend/src/modules/finance/components/projects/ProjectStatusBadge.vue`
- Create: `frontend/src/modules/finance/components/projects/ProjectSummaryCards.vue`
- Create: `frontend/src/modules/finance/components/projects/ProjectWorkPanel.vue`
- Create: `frontend/src/modules/finance/components/projects/ProjectTimePanel.vue`
- Create: `frontend/src/modules/finance/components/projects/ProjectLedgerPanel.vue`
- Create: `frontend/src/modules/finance/components/projects/ProjectDocumentsPanel.vue`
- Create: `frontend/src/modules/finance/components/projects/ProjectDocumentPicker.vue`
- Create: `frontend/src/modules/finance/components/projects/ProjectNotesPanel.vue`
- Create: `frontend/src/modules/finance/components/projects/ProjectActivityTimeline.vue`
- Create: `frontend/src/modules/finance/projects/ProjectListPage.vue`
- Create: `frontend/src/modules/finance/projects/ProjectDetailPage.vue`
- Create: `frontend/src/modules/finance/projects/ProjectEditPage.vue`
- Create: `frontend/src/modules/finance/projects/routes.ts`
- Create: `frontend/src/modules/finance/projects/__tests__/ProjectListPage.test.ts`
- Create: `frontend/src/modules/finance/projects/__tests__/ProjectDetailPage.test.ts`
- Create: `frontend/src/modules/finance/projects/__tests__/ProjectEditPage.test.ts`
- Create: `backend/lang/de/finance-projects.php`
- Create: `backend/lang/en/finance-projects.php`
- Create: `backend/lang/ru/finance-projects.php`

**Interfaces:**
- Consumes: Task 11 store/composables and existing shared UI primitives.
- Produces: unmounted project routes with truthful async/conflict states and accessible project workflows.

- [x] **Step 1: Write failing page/component tests**

Cover URL filters and server pagination; create/edit; named status transitions/reopen; hierarchy move/cycle feedback; separately paged tasks/time/ledger; invoiced-time locks; server-provided multi-currency totals and integer formatting; document source filtering and attach/detach; missing-source snapshot display; append-only note composer with correction; activity cursor loading; owner-safe capability links; panel-specific errors; action-key retry; and version-conflict replacement requiring explicit retry.

- [x] **Step 2: Implement list and edit pages**

The list is server-paginated and does not rebuild the entire hierarchy in memory. Show parent breadcrumbs and direct-child navigation from API data. The editor never exposes status as a generic field; status buttons invoke named actions. Budget form converts decimal text to minor units at the boundary without using it for authoritative arithmetic.

- [x] **Step 3: Implement detail panels**

Use tabs `overview`, `work`, `ledger`, `documents`, `notes`, and `activity`. Documents display source/role, revision label, MIME/size/hash, attached/detached state, availability, and current-vs-snapshot metadata. Notes have no edit/delete controls; correction creates a new entry and visibly links to the original. Activities label project versus linked-document events.

- [x] **Step 4: Export but do not mount routes**

`routes.ts` exports children for `finance/projects`, `finance/projects/new`, `finance/projects/:project`, and `finance/projects/:project/edit`. Do not modify `frontend/src/router/index.ts`, `frontend/src/stores/finance.ts`, or `frontend/src/views/Finance.vue`; mounting/removal belongs to frontend cutover.

- [x] **Step 5: Run frontend and translation gates**

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

- [x] **Step 6: Commit**

```bash
git add frontend/src/modules/finance backend/lang/de/finance-projects.php backend/lang/en/finance-projects.php backend/lang/ru/finance-projects.php
git commit -m "feat(finance): add project workspace interface"
```

### Task 13: Define exact legacy mapping and project cutover gates

**Files:**
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyProjectExpenseRow.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyProjectExpenseParser.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyProjectMapper.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Compatibility/LegacyProjectDiagnostic.php`
- Create: `backend/tests/Feature/FinanceModule/Projects/LegacyProjectMapperTest.php`
- Create: `docs/finance/projects-documents-notes.md`

**Interfaces:**
- Consumes: legacy project/task/time/expense data and owner-scoped file/photo/receipt/transaction/quote/invoice references.
- Produces: deterministic per-project mapping plus diagnostics and activation gates for the global migration plan; it does not run bulk migration.

- [x] **Step 1: Write failing legacy fixtures**

Cover root/subproject, missing/deleted parent, every status/kind, archived row, partner/quote reference, null/negative/large budget, mutable note, valid and malformed expense JSON, legacy rows without direction/title/id, numeric and string decimals, unsupported scale/exponent, tasks/milestones, negative time correction, frozen/missing rate, invoiced time, files, photos, standalone receipts, transaction receipts, bank transactions, missing blobs, foreign references, and repeated `(user_id, source_type, source_id)` mapping.

- [x] **Step 2: Implement exact expense parsing**

Read `FinanceProject::getRawOriginal('expenses')`, tokenize JSON strings/escapes/delimiters/numeric lexemes, and pass amount lexemes to `Money::fromDecimal`; never hydrate authoritative amounts through PHP float. Preserve unknown keys under `legacy_metadata`. Reject malformed JSON, exponent notation, more than two decimal places, invalid direction, or currency ambiguity with a blocking diagnostic.

```php
final class LegacyProjectExpenseParser
{
    /** @return list<LegacyProjectExpenseRow> */
    public function parse(string $rawJson, string $currency): array;
}
```

- [x] **Step 3: Implement idempotent project mapping**

Use `source_type=legacy.finance_project` and legacy ID. Map the mutable note once to initial internal `project_note`; tasks/time/ledger to structured rows; quote as unresolved pinned source until quote migration resolves its revision; invoiced time to an opaque unresolved target; and external evidence to document links with verified metadata/hash when available. Missing files, cross-owner links, unknown currency, broken task/project links, and amounts that cannot be exact are blocking diagnostics rather than silent skips.

- [x] **Step 4: Document compatibility and activation gates**

Record schema, DTOs, commands, route contracts, status maps, source adapters, note/activity rules, link history, lock order, idempotency, and these cutover gates:

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

- [x] **Step 5: Run mapper and compatibility tests**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule/Projects/LegacyProjectMapperTest.php tests/Feature/FinanceProjectPlanTest.php tests/Feature/FinanceRelationalTest.php tests/Feature/FinanceScopeTest.php
vendor/bin/pint --test app/Modules/Finance/Infrastructure/Compatibility tests/Feature/FinanceModule/Projects
```

Expected: PASS.

- [x] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance/Infrastructure/Compatibility backend/tests/Feature/FinanceModule/Projects/LegacyProjectMapperTest.php docs/finance/projects-documents-notes.md
git commit -m "docs(finance): define project migration boundary"
```

### Task 14: Run complete verification and record the downstream handoff

**Files:**
- Modify: `docs/finance/projects-documents-notes.md`
- Modify: `docs/superpowers/plans/2026-08-28-finance-projects-documents-notes.md`

**Interfaces:**
- Consumes: Tasks 1–13.
- Produces: verified handoff for quote integration, invoices/payments, global migration, frontend cutover, legacy removal, and release.

- [x] **Step 1: Run focused backend suites**

Run:

```bash
cd backend
FILES_DISK=local php artisan test tests/Feature/FinanceModule tests/Unit/Modules/Finance tests/Feature/FinanceProjectPlanTest.php tests/Feature/FinanceRelationalTest.php tests/Feature/FinanceScopeTest.php tests/Feature/FinanceQuoteTest.php tests/Feature/FilesRelationalTest.php tests/Feature/NotesFeatureTest.php tests/Feature/Guards/ApiSurfaceGuardTest.php
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

Expected: PASS. If an unrelated environment failure exists, record the exact failing test, environment requirement, and proof that it also fails on this plan's base commit; do not mark the step complete without that evidence.

- [x] **Step 5: Update verification record and completed checkboxes**

Record commands, date, test/assertion counts, formatter/static-analysis results, frontend results, quote-port/invoice-port bindings, and clean status. Mark a checkbox only after its command succeeds.

- [x] **Step 6: Commit**

```bash
git add docs/finance/projects-documents-notes.md docs/superpowers/plans/2026-08-28-finance-projects-documents-notes.md
git commit -m "docs(finance): verify project workflow"
```

## Dependencies, non-overlap, and downstream ownership

- The foundation at `docs/finance/module-foundation.md` is mandatory: project document links depend on owner-composite document-series/revision keys, canonical immutable snapshots, and the established module dependency direction.
- The quote workflow must call `ProjectFromQuoteTarget` only after its own rules have established an eligible immutable revision. This plan does not change quote status, expiry, pending-draft, revision, delivery, numbering, PDF, or invoice-conversion code. Before enabling quote-to-project UI, the quote work must resolve how its single `converted` state represents the design requirement that one accepted revision may independently produce a project and/or invoice; project code must not guess.
- The invoice/payments rewrite replaces `LegacyInvoiceDraftFromTimeAdapter` with a modular adapter retaining `ProjectToInvoicePort`. It owns invoice totals, finalization, numbering, PDF, stock, payments, dunning, cancellation, and invoice-side project links.
- Files, Gallery, receipts, and bank transactions keep ownership of bytes and mutable source metadata. Project source adapters remain read-only; attach/detach changes only `finance_project_document_links` and project history.
- The generic Notes module remains independent. It is not used to store project/document notes because its editable/soft-deletable semantics conflict with append-only Finance history.
- The global legacy-migration plan owns batch orchestration, progress markers, retries, cross-module quote/invoice reference resolution, checksums, and activation approval. It calls `LegacyProjectMapper`; it does not duplicate parsing/mapping rules.
- The frontend-cutover plan mounts `frontend/src/modules/finance/projects/routes.ts`, switches the canonical API alias, removes project consumers of `/finance/data`, and runs browser workflows against migrated data.
- The legacy-removal plan removes old project controllers/routes/models/store/view code only after rollback/parity windows close. Historical migrations remain so older installations can upgrade.

## Principal risks to verify during implementation

- The quote plan's current terminal `converted` state conflicts with independent project-and/or-invoice conversion. Treat this as an interface dependency, not a project-module workaround.
- SQLite cannot express every PostgreSQL partial/composite constraint directly; paired trigger tests must prove equivalent owner and active-link behavior.
- Unified source pagination can become unstable if adapters return inconsistent sort keys. Normalize all source timestamps and tie-break by source type/reference before exposing cursors.
- Link-time metadata snapshots can contain sensitive source fields. Build snapshots from explicit allowlists and test that raw paths, OCR, customer bodies, and secrets never enter them.
- Legacy expense JSON may contain float-shaped tokens or unknown keys. Parse raw lexemes, stop on non-exact values, and compare per-project/per-currency control totals before cutover.
- Project timelines merge two append-only stores. Cursor order and de-duplication must remain deterministic under concurrent activity inserts.
- Compatibility invoice drafts are a temporary boundary. Their target references must be stable enough for migration, while all invoice authority stays outside the project module.
