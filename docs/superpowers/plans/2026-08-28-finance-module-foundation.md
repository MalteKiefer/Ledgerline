# Finance Module Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish the isolated Finance module, cent-exact document calculator, workflow primitives, and immutable document-series persistence required by the complete Finance rewrite.

**Architecture:** The new code lives under `App\Modules\Finance` and does not depend on legacy Finance controllers or Vue code. Domain objects are framework-independent; Laravel adapters provide persistence and HTTP integration. This foundation is additive and has no production cutover, so the current Finance UI remains operational while later plans build on stable interfaces.

**Tech Stack:** PHP 8.5, Laravel 13.8, Eloquent, PostgreSQL production, SQLite test database, PHPUnit 13, Laravel Pint, PHPStan/Larastan.

**Spec:** `docs/superpowers/specs/2026-08-28-finance-module-rewrite-design.md`

## Global Constraints

- Authoritative money values use integer minor units; floating-point arithmetic is forbidden in Domain and Application code.
- Domain code must not import Laravel HTTP, Eloquent, filesystem, mail, queue, or Vue concerns.
- Every persisted Finance aggregate is owner-scoped by `user_id` or an owner-validated aggregate relation.
- Published document revisions and their PDF identity are immutable.
- Workflow state changes occur only through named commands, never generic CRUD updates.
- New persistence is additive until a later migration plan completes exact control-total checks.
- Existing Finance behavior and tests remain green throughout this foundation.
- No deployment, push, version bump, or release tag occurs in this plan.

---

### Task 1: Freeze the legacy baseline and repair deterministic test bootstrap

**Files:**
- Modify: `backend/phpunit.xml`
- Create: `backend/tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php`

**Interfaces:**
- Consumes: existing quote, invoice, project, recurring, PDF, and mail routes.
- Produces: deterministic Laravel encryption bootstrap and a compact regression contract for later cutover plans.

- [ ] **Step 1: Write a failing bootstrap assertion**

Create `LegacyFinanceBaselineTest` using `RefreshDatabase`. Assert
`config('app.key')` is a valid non-empty test key and exercise one encrypted
`UserSetting` value. The first run without an environment key must reproduce the
current `Unsupported cipher or incorrect key length` failure.

```php
public function test_finance_tests_have_a_deterministic_application_key(): void
{
    $this->assertMatchesRegularExpression('/^base64:/', (string) config('app.key'));
    $user = User::factory()->create();
    UserSetting::for((int) $user->id)->forceFill(['smtp_password' => 'test-secret'])->save();
    $this->assertSame('test-secret', UserSetting::for((int) $user->id)->smtp_password);
}
```

- [ ] **Step 2: Run the test without a shell-provided key**

Run: `cd backend && php artisan test tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php`

Expected: FAIL with invalid key length before `phpunit.xml` is changed.

- [ ] **Step 3: Add a test-only application key**

Add this exact entry inside the existing `<php>` block in `phpunit.xml`:

```xml
<env name="APP_KEY" value="base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY="/>
```

Do not change `.env`, `.env.example`, or runtime secrets.

- [ ] **Step 4: Add baseline workflow assertions**

In the same test class, cover these existing contracts with minimal fixtures:

- sent quote cannot be edited;
- quote conversion is idempotent;
- project time can only be invoiced once;
- invoice finalization allocates one number on retry;
- finalized invoice can be cancelled only once.

Use named API routes and assert both response and persisted row counts.

- [ ] **Step 5: Run focused legacy suites**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php tests/Feature/FinanceQuoteTest.php tests/Feature/FinanceProjectPlanTest.php tests/Feature/InvoiceStornoTest.php
```

Expected: PASS with no shell-provided `APP_KEY`.

- [ ] **Step 6: Commit**

```bash
git add backend/phpunit.xml backend/tests/Feature/FinanceModule/LegacyFinanceBaselineTest.php
git commit -m "test(finance): establish deterministic legacy baseline"
```

### Task 2: Bootstrap the isolated Finance module

**Files:**
- Create: `backend/app/Modules/Finance/FinanceServiceProvider.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/FinanceModule.php`
- Create: `backend/app/Modules/Finance/Http/Routes/api.php`
- Create: `backend/app/Modules/Finance/Http/Controllers/HealthController.php`
- Create: `backend/app/Modules/Finance/Http/Resources/FinanceModuleResource.php`
- Modify: `backend/bootstrap/providers.php`
- Create: `backend/tests/Feature/FinanceModule/FinanceModuleBootstrapTest.php`

**Interfaces:**
- Consumes: Laravel service-provider registration and authenticated `module:finance` middleware.
- Produces: `FinanceServiceProvider`, route name `api.finance-v2.health`, and version constant `FinanceModule::SCHEMA_VERSION = 1`.

- [ ] **Step 1: Write failing module-boundary tests**

Assert the provider is registered, the authenticated endpoint
`GET /api/v1/finance-v2/health` returns `{module:"finance", schemaVersion:1}`
for a user with Finance enabled, rejects unauthenticated access, and contains no
legacy snapshot data.

```php
$this->actingAs(User::factory()->create())
    ->getJson(route('api.finance-v2.health'))
    ->assertOk()
    ->assertExactJson(['module' => 'finance', 'schemaVersion' => 1]);
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/FinanceModule/FinanceModuleBootstrapTest.php`

Expected: FAIL because the provider and route do not exist.

- [ ] **Step 3: Implement provider and route loading**

`FinanceServiceProvider::boot()` loads the module route file. The route file
uses prefix `/api/v1/finance-v2`, names `api.finance-v2.*`, and applies the same
authentication, device-ability, throttle, and `module:finance` middleware as the
existing Finance API group. `HealthController` returns only the Resource.

- [ ] **Step 4: Enforce dependency direction mechanically**

Add a test that scans PHP files below `Domain/` and fails if their source contains
any of these namespaces:

```text
Illuminate\Http
Illuminate\Database\Eloquent
Illuminate\Support\Facades
Symfony\Component\HttpFoundation
```

Laravel-independent PHP standard-library imports remain allowed.

- [ ] **Step 5: Run tests and formatter**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/FinanceModuleBootstrapTest.php
vendor/bin/pint --test app/Modules/Finance tests/Feature/FinanceModule
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance backend/bootstrap/providers.php backend/tests/Feature/FinanceModule/FinanceModuleBootstrapTest.php
git commit -m "feat(finance): bootstrap isolated finance module"
```

### Task 3: Implement exact decimal and money value objects

**Files:**
- Create: `backend/app/Modules/Finance/Domain/Shared/Money.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/DecimalQuantity.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/Rounding.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/Exception/InvalidMoney.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/Exception/InvalidQuantity.php`
- Create: `backend/tests/Unit/Modules/Finance/Domain/Shared/MoneyTest.php`
- Create: `backend/tests/Unit/Modules/Finance/Domain/Shared/DecimalQuantityTest.php`

**Interfaces:**
- Consumes: canonical decimal strings from requests and persistence.
- Produces: `Money::fromDecimal(string,string)`, `Money::fromMinor(int,string)`, `Money::minor()`, `Money::currency()`, `Money::add(Money)`, `Money::subtract(Money)`, and `DecimalQuantity::fromString(string)` with scale 4.

- [ ] **Step 1: Write failing Money tests**

Test exact parsing of `0`, `0.01`, `119.00`, `-0.01`, and the largest supported
14,2 database value. Reject comma decimals, exponent notation, three fraction
digits, invalid ISO currency, and overflow. Assert cross-currency addition throws.

```php
$this->assertSame(11900, Money::fromDecimal('119.00', 'eur')->minor());
$this->assertSame('EUR', Money::fromDecimal('119.00', 'eur')->currency());
```

- [ ] **Step 2: Write failing quantity tests**

Accept signed values with at most four decimals and expose the scaled integer.
Test `1 -> 10000`, `1.5 -> 15000`, `0.0001 -> 1`; reject exponent notation,
comma decimals, and five fraction digits.

- [ ] **Step 3: Verify failure**

Run: `cd backend && php artisan test tests/Unit/Modules/Finance/Domain/Shared`

Expected: FAIL because the value objects do not exist.

- [ ] **Step 4: Implement without floats**

Parse with anchored regular expressions, split sign/whole/fraction, right-pad to
the required scale, and use checked integer multiplication/addition. Normalize
currency to uppercase `^[A-Z]{3}$`. Implement `Rounding::halfAwayFromZero(int
$numerator, int $denominator): int` using integer quotient and remainder.

```php
final readonly class Money
{
    private function __construct(private int $minor, private string $currency) {}
    public static function fromDecimal(string $amount, string $currency): self;
    public static function fromMinor(int $minor, string $currency): self;
    public function minor(): int;
    public function currency(): string;
    public function add(self $other): self;
    public function subtract(self $other): self;
}
```

- [ ] **Step 5: Run tests and quality checks**

Run:

```bash
cd backend
php artisan test tests/Unit/Modules/Finance/Domain/Shared
vendor/bin/pint --test app/Modules/Finance/Domain/Shared tests/Unit/Modules/Finance/Domain/Shared
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance/Domain/Shared backend/tests/Unit/Modules/Finance/Domain/Shared
git commit -m "feat(finance): add exact money value objects"
```

### Task 4: Build the authoritative document calculator

**Files:**
- Create: `backend/app/Modules/Finance/Domain/Shared/DocumentLine.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/Discount.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/TaxBreakdown.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/DocumentTotals.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/DocumentCalculator.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/Exception/InvalidDocument.php`
- Create: `backend/tests/Unit/Modules/Finance/Domain/Shared/DocumentCalculatorTest.php`

**Interfaces:**
- Consumes: `list<DocumentLine>`, currency, and `Discount::none|percentBasisPoints|fixed(Money)`.
- Produces: `DocumentCalculator::calculate(array $lines, Discount $discount): DocumentTotals` with exact net, VAT, gross, discount, and VAT-by-rate minor units.

- [ ] **Step 1: Write failing line-calculation tests**

Cover quantity times unit price, negative credit lines, zero VAT, 7 percent,
19 percent, mixed rates, and empty documents. Use exact expected minor units.

```php
$totals = $calculator->calculate([
    new DocumentLine('Service', DecimalQuantity::fromString('2.5'), Money::fromDecimal('100.00', 'EUR'), 1900),
], Discount::none('EUR'));
$this->assertSame(25000, $totals->net->minor());
$this->assertSame(4750, $totals->vat->minor());
$this->assertSame(29750, $totals->gross->minor());
```

- [ ] **Step 2: Write failing discount-distribution tests**

Cover a 10-percent discount, a fixed discount across 7/19-percent groups,
remainder-cent assignment, discount equal to total net, discount exceeding net,
and negative discounts. Require deterministic distribution ordered by tax rate
then original line position.

- [ ] **Step 3: Verify failure**

Run: `cd backend && php artisan test tests/Unit/Modules/Finance/Domain/Shared/DocumentCalculatorTest.php`

Expected: FAIL because the calculator does not exist.

- [ ] **Step 4: Implement integer-only calculation**

Calculate line net with scaled integer multiplication and
`Rounding::halfAwayFromZero`. Distribute fixed discounts proportionally by raw
net weight; assign remaining cents deterministically. Calculate VAT per rate from
discounted taxable bases. Reject empty lines, mixed currencies, negative tax
rates, rates above 10000 basis points, and discounts outside `[0, net]`.

- [ ] **Step 5: Add client-control-sum comparison**

Implement:

```php
public function matchesControlTotals(?Money $net, ?Money $vat, ?Money $gross): bool
```

Null controls mean no comparison. Every supplied value must equal the computed
value exactly.

- [ ] **Step 6: Run tests and formatter**

Run:

```bash
cd backend
php artisan test tests/Unit/Modules/Finance/Domain/Shared/DocumentCalculatorTest.php
vendor/bin/pint --test app/Modules/Finance/Domain/Shared tests/Unit/Modules/Finance/Domain/Shared
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Modules/Finance/Domain/Shared backend/tests/Unit/Modules/Finance/Domain/Shared/DocumentCalculatorTest.php
git commit -m "feat(finance): add authoritative document calculator"
```

### Task 5: Define reusable workflow transition primitives

**Files:**
- Create: `backend/app/Modules/Finance/Domain/Shared/Workflow/Transition.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/Workflow/StateMachine.php`
- Create: `backend/app/Modules/Finance/Domain/Shared/Workflow/Exception/InvalidTransition.php`
- Create: `backend/tests/Unit/Modules/Finance/Domain/Shared/Workflow/StateMachineTest.php`

**Interfaces:**
- Consumes: a map of current states to allowed named target states.
- Produces: `StateMachine::assertCan(string $from, string $to): void` and `StateMachine::can(string $from, string $to): bool`.

- [ ] **Step 1: Write failing transition tests**

Instantiate a state machine with `draft -> sent`, `sent -> accepted|declined`,
and `accepted -> converted`. Assert allowed transitions, rejected reverse and
self transitions, unknown states, and stable exception properties `from` and
`to`.

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Unit/Modules/Finance/Domain/Shared/Workflow/StateMachineTest.php`

Expected: FAIL because the workflow classes do not exist.

- [ ] **Step 3: Implement immutable transition map**

Normalize and validate the map in the constructor, preserve exact state names,
and make `assertCan` throw `InvalidTransition` with code `invalid_transition`.
Do not include quote- or invoice-specific states in this shared class.

- [ ] **Step 4: Run tests**

Run: `cd backend && php artisan test tests/Unit/Modules/Finance/Domain/Shared/Workflow/StateMachineTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/Finance/Domain/Shared/Workflow backend/tests/Unit/Modules/Finance/Domain/Shared/Workflow
git commit -m "feat(finance): add workflow state machine"
```

### Task 6: Add document-series, revision, activity, and note schema

**Files:**
- Create: `backend/database/migrations/2026_08_28_100000_create_finance_document_core.php`
- Create: `backend/tests/Feature/FinanceModule/DocumentCoreSchemaTest.php`

**Interfaces:**
- Consumes: existing `users` table and PostgreSQL/SQLite-compatible Laravel schema APIs.
- Produces: `finance_document_series`, `finance_document_revisions`, `finance_document_activities`, and `finance_document_notes`.

- [ ] **Step 1: Write failing schema tests**

Assert required tables and columns. Verify unique `(user_id, uuid)`, unique
`(document_series_id, revision_number)`, revision self-reference, indexes for
owner/type/status and owner/timestamp, and foreign-key deletion behavior.

Required revision columns include:

```text
document_series_id, revision_number, previous_revision_id, status,
snapshot, net_minor, vat_minor, gross_minor, currency, change_reason,
pdf_path, pdf_sha256, published_at, created_by, created_at
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/FinanceModule/DocumentCoreSchemaTest.php`

Expected: FAIL because the tables do not exist.

- [ ] **Step 3: Implement additive schema**

Use bigint minor-unit columns, three-character currency, JSON snapshot, nullable
server-owned PDF metadata, and `restrictOnDelete` from revisions to series.
Activities and notes cascade with their series. Notes use visibility enum values
`internal` and `customer`. Add `source_type`/`source_id` to series for migration
idempotency with a unique owner/source key.

- [ ] **Step 4: Run migration cycle and schema tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/DocumentCoreSchemaTest.php
php artisan migrate:fresh --env=testing --force
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_08_28_100000_create_finance_document_core.php backend/tests/Feature/FinanceModule/DocumentCoreSchemaTest.php
git commit -m "feat(finance): add document core schema"
```

### Task 7: Implement persistence models and immutable revision guard

**Files:**
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/DocumentSeriesRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/DocumentRevisionRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/DocumentActivityRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Models/DocumentNoteRecord.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/Exception/PublishedRevisionMutation.php`
- Create: `backend/tests/Feature/FinanceModule/DocumentPersistenceTest.php`

**Interfaces:**
- Consumes: schema from Task 6 and existing owner-assignment behavior.
- Produces: typed relations plus a hard mutation/delete guard for published revisions.

- [ ] **Step 1: Write failing owner-isolation tests**

Create series for two users, authenticate as each user, and assert repository
queries never expose the other user's series, revisions, activities, or notes.
Verify a child ID from another owner cannot be resolved through an owned series.

- [ ] **Step 2: Write failing immutability tests**

Assert a draft revision can be updated. After `published_at` is set, attempts to
update, delete, replace `pdf_path`, or alter `snapshot` throw
`PublishedRevisionMutation`. Assert activities cannot be updated or deleted.

- [ ] **Step 3: Verify failure**

Run: `cd backend && php artisan test tests/Feature/FinanceModule/DocumentPersistenceTest.php`

Expected: FAIL because models do not exist.

- [ ] **Step 4: Implement records and guards**

Keep `user_id`, hashes, publication fields, source identity, and revision number
out of `$fillable`. Use explicit casts for snapshot, integer minor units, and
timestamps. Register Eloquent model-event guards before write/delete. Resolve
child ownership through a required owner-scoped series query rather than trusting
the child's numeric ID.

- [ ] **Step 5: Run tests and formatter**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule/DocumentPersistenceTest.php
vendor/bin/pint --test app/Modules/Finance/Infrastructure/Persistence tests/Feature/FinanceModule
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Finance/Infrastructure/Persistence backend/tests/Feature/FinanceModule/DocumentPersistenceTest.php
git commit -m "feat(finance): persist immutable document revisions"
```

### Task 8: Create and publish document revisions through an application port

**Files:**
- Create: `backend/app/Modules/Finance/Application/DTOs/CreateRevisionData.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/DocumentRevisionId.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/PublishedRevision.php`
- Create: `backend/app/Modules/Finance/Application/DTOs/StoredDocument.php`
- Create: `backend/app/Modules/Finance/Application/Ports/DocumentRevisionRepository.php`
- Create: `backend/app/Modules/Finance/Application/Ports/DocumentRenderer.php`
- Create: `backend/app/Modules/Finance/Application/Ports/DocumentStorage.php`
- Create: `backend/app/Modules/Finance/Application/Commands/CreateDocumentRevision.php`
- Create: `backend/app/Modules/Finance/Application/Commands/PublishDocumentRevision.php`
- Create: `backend/app/Modules/Finance/Infrastructure/Persistence/EloquentDocumentRevisionRepository.php`
- Create: `backend/tests/Feature/FinanceModule/DocumentRevisionApplicationTest.php`
- Modify: `backend/app/Modules/Finance/FinanceServiceProvider.php`

**Interfaces:**
- Consumes: calculated `DocumentTotals`, canonical snapshot arrays, repository port, and renderer port.
- Produces: `CreateDocumentRevision::handle(CreateRevisionData): DocumentRevisionId`, `PublishDocumentRevision::handle(DocumentRevisionId): PublishedRevision`, and storage methods `putPdf(string $seriesUuid, string $bytes): StoredDocument` / `delete(string $path): void`.

- [ ] **Step 1: Write failing revision-creation tests**

Assert first revision is number 1, the next references revision 1, concurrent
creation cannot duplicate a number, control totals are persisted from
`DocumentCalculator`, canonical JSON produces a stable SHA-256, and another
owner's series is rejected.

- [ ] **Step 2: Write failing publication tests with a fake renderer**

The fake renderer returns deterministic `%PDF-test` bytes. Assert publication
stores a server-generated safe path, byte SHA-256, and timestamp atomically;
retry returns the same published revision without a second render. Renderer
failure leaves the revision unpublished and records no false success activity.

- [ ] **Step 3: Verify failure**

Run: `cd backend && php artisan test tests/Feature/FinanceModule/DocumentRevisionApplicationTest.php`

Expected: FAIL because commands and ports do not exist.

- [ ] **Step 4: Implement DTOs, ports, repository, and commands**

Canonicalize snapshot keys recursively before JSON encoding. Execute sequence
allocation and writes in a DB transaction with row locks and a unique-constraint
retry. `DocumentRevisionId` is a readonly positive-integer value object.
`PublishDocumentRevision` calls the renderer once, writes bytes through the
`DocumentStorage` port, then stores path/hash and appends `revision.published`.
On transaction failure, call `DocumentStorage::delete()` for newly written bytes.

- [ ] **Step 5: Register interfaces**

Bind `DocumentRevisionRepository` to `EloquentDocumentRevisionRepository` in
`FinanceServiceProvider`. Register the test fake renderer within the test only;
the production PDF adapter is delivered in the invoice/quote plans.

- [ ] **Step 6: Run focused and foundation tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule tests/Unit/Modules/Finance
vendor/bin/pint --test app/Modules/Finance tests/Feature/FinanceModule tests/Unit/Modules/Finance
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Modules/Finance backend/tests/Feature/FinanceModule/DocumentRevisionApplicationTest.php
git commit -m "feat(finance): add document revision application services"
```

### Task 9: Verify and document the module foundation

**Files:**
- Create: `docs/finance/module-foundation.md`
- Modify: `docs/superpowers/plans/2026-08-28-finance-module-foundation.md`

**Interfaces:**
- Consumes: Tasks 1–8.
- Produces: verified module boundary and documented interfaces for downstream plans.

- [x] **Step 1: Run complete relevant regression suites**

Run:

```bash
cd backend
php artisan test tests/Feature/FinanceModule tests/Unit/Modules/Finance tests/Feature/FinanceQuoteTest.php tests/Feature/FinanceProjectPlanTest.php tests/Feature/FinanceRecurringTest.php tests/Feature/FinanceReportsTest.php tests/Feature/TaxReportsTest.php tests/Feature/InvoiceDiscountTest.php tests/Feature/InvoiceDunTest.php tests/Feature/InvoiceEmailTest.php tests/Feature/InvoiceReminderTest.php tests/Feature/InvoiceStornoTest.php tests/Feature/InvoiceVersionPdfTest.php
```

Expected: PASS.

- [x] **Step 2: Run project quality gates affected by this plan**

Run:

```bash
cd backend
vendor/bin/pint --test app/Modules/Finance tests/Feature/FinanceModule tests/Unit/Modules/Finance
vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G
```

Expected: both commands exit zero.

- [x] **Step 3: Document stable contracts**

Document exact namespace boundaries, Money and DecimalQuantity formats,
rounding rule, calculator inputs/outputs, workflow API, schema ownership,
revision immutability, repository and renderer ports, and the commands executed
for verification. State explicitly that production routes still use the legacy
Finance implementation until later cutover.

- [x] **Step 4: Mark completed checklist entries and commit**

Only mark a checkbox after its command has succeeded.

```bash
git add docs/finance/module-foundation.md docs/superpowers/plans/2026-08-28-finance-module-foundation.md
git commit -m "docs(finance): document module foundation"
```

## Subsequent implementation plans

After this foundation is reviewed, create and execute detailed plans in this
dependency order. Each plan must retain the same global constraints and deliver
working, independently reviewed software:

1. `finance-quotes-rewrite`: quote aggregate, workflow, numbering, revision UI, PDF, mail, and idempotent conversion ports.
2. `finance-projects-rewrite`: project aggregate, tasks, time, project documents, structured notes, and quote conversion.
3. `finance-invoices-payments-rewrite`: invoice workflow, finalization, immutable PDF, storno, payment allocations, dunning, and project/quote links.
4. `finance-recurring-invoices`: versioned templates, scheduler, draft and auto-send modes, run log, retry, and management UI.
5. `finance-import-pipeline`: common import contracts, CSV, MT940, preview, deduplication, and import log.
6. `finance-legacy-migration`: resumable per-user migration, file hashes, exact control totals, and activation gate.
7. `finance-frontend-cutover`: routed Vue module, paginated APIs/stores, browser workflows, and removal of snapshot dependencies.
8. `finance-legacy-removal`: remove old runtime controllers/routes/store/view/services after migration tests prove parity.
9. `finance-release-1.785.0`: full regression, lint/static/security/build gates, changelog/version, final review, push, CI wait, tag, and tag-build wait.
