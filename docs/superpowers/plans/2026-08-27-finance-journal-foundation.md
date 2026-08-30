# Finance Journal Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the owner-scoped, cent-exact, immutable EÜR journal foundation and post finalized outgoing invoices into it atomically.

**Architecture:** Existing finance documents remain workflow aggregates. A new application service translates a finalized document into an idempotent `FinanceEvent` with a balanced, immutable `JournalEntry` and `JournalLine` set. The first slice posts outgoing EUR invoices; later plans add migration, receipts, banking, OPOS, DATEV, FX, reports, and frontend decomposition.

**Tech Stack:** PHP 8.5, Laravel 13.8, Eloquent, SQLite test database, PostgreSQL production database, PHPUnit 13.

**Spec:** `docs/superpowers/specs/2026-08-27-finance-journal-design.md`

## Global Constraints

- Store money in integer minor units; never use floating-point arithmetic in journal code.
- Posted entries and lines are immutable and cannot be soft-deleted.
- Every journal query and relation must preserve `user_id` ownership.
- Every entry must balance debit and credit totals in base currency EUR.
- Posting must be idempotent by owner, source type, source ID, and event kind.
- Controllers contain no posting calculations; transaction boundaries live in application services.
- All existing finance behavior and tests must remain green.
- No intermediate deployment is performed.

---

### Task 1: Characterize invoice finalization

**Files:**
- Create: `backend/tests/Feature/Finance/InvoiceFinalizationCharacterizationTest.php`
- Inspect: `backend/app/Http/Controllers/FinanceController.php`

**Interfaces:**
- Consumes: existing named routes `finance.invoices.store` and `finance.invoices.finalize`.
- Produces: regression contract for invoice totals, numbering, retry behavior, and failed finalization.

- [ ] **Step 1: Write characterization tests**

Create tests using `RefreshDatabase` that create a user, create a draft invoice
with one `100.00` net line and `19.00` VAT, then assert finalization returns
`sent`, stores `net=100.00`, `vat=19.00`, `gross=119.00`, assigns one number,
and a second finalize call does not allocate another number. Add a validation
case proving an invalid draft creates neither a finalized invoice nor a number.

```php
$response = $this->postJson(route('finance.invoices.finalize', $invoiceId));
$response->assertOk()->assertJsonPath('invoice.status', 'sent');
$invoice = Invoice::findOrFail($invoiceId);
$this->assertSame('100.00', $invoice->net);
$this->assertSame('19.00', $invoice->vat);
$this->assertSame('119.00', $invoice->gross);
```

- [ ] **Step 2: Run the focused characterization test**

Run: `cd backend && php artisan test tests/Feature/Finance/InvoiceFinalizationCharacterizationTest.php`

Expected: PASS against the current implementation. If it fails, record the
actual current contract in the test without weakening money or numbering
assertions.

- [ ] **Step 3: Run the existing invoice lifecycle tests**

Run: `cd backend && php artisan test tests/Feature/FinanceRelationalTest.php tests/Feature/InvoiceStornoTest.php tests/Feature/InvoiceVersionPdfTest.php`

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add backend/tests/Feature/Finance/InvoiceFinalizationCharacterizationTest.php
git commit -m "test(finance): characterize invoice finalization"
```

### Task 2: Add cent-exact money primitives

**Files:**
- Create: `backend/app/Domain/Finance/Money.php`
- Create: `backend/app/Domain/Finance/JournalLineData.php`
- Create: `backend/tests/Unit/Domain/Finance/MoneyTest.php`

**Interfaces:**
- Consumes: decimal strings from Eloquent such as `"119.00"`.
- Produces: `Money::fromDecimal(string $amount, string $currency): Money`, `Money::minor(): int`, `Money::currency(): string`, and `JournalLineData`.

- [ ] **Step 1: Write failing parsing tests**

```php
$this->assertSame(11900, Money::fromDecimal('119.00', 'eur')->minor());
$this->assertSame(-1, Money::fromDecimal('-0.01', 'EUR')->minor());
$this->assertSame('EUR', Money::fromDecimal('1', 'eur')->currency());
```

Also assert rejection of `1.001`, scientific notation, empty values, unsupported
currency length, and integer overflow.

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Unit/Domain/Finance/MoneyTest.php`

Expected: FAIL because `Money` does not exist.

- [ ] **Step 3: Implement decimal parsing without floats**

Validate with `^-?[0-9]+(?:\.[0-9]{1,2})?$`, split whole and fraction, right-pad
the fraction to two digits, and construct minor units using checked integer
operations. Normalize currency with `strtoupper` and require `^[A-Z]{3}$`.

```php
final readonly class Money
{
    private function __construct(private int $minor, private string $currency) {}
    public static function fromDecimal(string $amount, string $currency): self { /* checked string parsing */ }
    public static function fromMinor(int $minor, string $currency): self { /* normalize currency */ }
    public function minor(): int { return $this->minor; }
    public function currency(): string { return $this->currency; }
}
```

Define `JournalLineData` as a readonly DTO with account ID, debit/credit side,
base minor amount, transaction minor amount, currency, tax metadata, and optional
partner/project/category IDs.

- [ ] **Step 4: Run tests and formatter**

Run: `cd backend && php artisan test tests/Unit/Domain/Finance/MoneyTest.php && vendor/bin/pint --test app/Domain/Finance tests/Unit/Domain/Finance`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Finance backend/tests/Unit/Domain/Finance
git commit -m "feat(finance): add cent-exact money primitives"
```

### Task 3: Create journal schema

**Files:**
- Create: `backend/database/migrations/2026_08_27_140000_create_finance_journal_tables.php`
- Create: `backend/tests/Feature/Finance/JournalSchemaTest.php`

**Interfaces:**
- Consumes: existing `users`, `finance_partners`, `finance_projects`, and `finance_categories` tables.
- Produces: `finance_accounts`, `finance_tax_codes`, `finance_events`, `journal_entries`, and `journal_lines`.

- [ ] **Step 1: Write failing schema tests**

Assert all five tables and their required columns exist. Insert two events with
the same `(user_id, source_type, source_id, kind)` and assert the second insert
raises `QueryException`. Prove the same source ID is allowed for another user.

```php
$this->assertTrue(Schema::hasColumns('journal_lines', [
    'journal_entry_id', 'finance_account_id', 'side', 'amount_minor',
    'base_amount_minor', 'currency', 'base_currency', 'tax_rate_bps',
]));
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/Finance/JournalSchemaTest.php`

Expected: FAIL because the tables do not exist.

- [ ] **Step 3: Implement one ordered migration**

Use `foreignId()->constrained()->cascadeOnDelete()` for user-owned aggregate
roots, `restrictOnDelete()` for accounts referenced by journal lines, signed
`bigInteger` minor-unit columns, `decimal('exchange_rate', 20, 10)`, and indexes
for `(user_id, fiscal_year, posted_on)` and report dimensions. Add the unique
source-event key and unique `(user_id, fiscal_year, journal_number)` key.

The `down()` method drops tables in exact reverse foreign-key order.

- [ ] **Step 4: Run schema tests and migration cycle**

Run: `cd backend && php artisan test tests/Feature/Finance/JournalSchemaTest.php && php artisan migrate:fresh --env=testing --force`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_08_27_140000_create_finance_journal_tables.php backend/tests/Feature/Finance/JournalSchemaTest.php
git commit -m "feat(finance): add journal schema"
```

### Task 4: Add owner-scoped journal models and immutability guards

**Files:**
- Create: `backend/app/Models/FinanceAccount.php`
- Create: `backend/app/Models/FinanceTaxCode.php`
- Create: `backend/app/Models/FinanceEvent.php`
- Create: `backend/app/Models/JournalEntry.php`
- Create: `backend/app/Models/JournalLine.php`
- Create: `backend/app/Domain/Finance/Exceptions/PostedJournalMutation.php`
- Create: `backend/tests/Feature/Finance/JournalModelTest.php`

**Interfaces:**
- Consumes: schema from Task 3 and `OwnsUserData`.
- Produces: typed Eloquent relations and mutation-protected posted records.

- [ ] **Step 1: Write failing ownership and immutability tests**

Create records for two users and prove authenticated queries only return the
current user's records. Assert `save()`, `delete()`, and line mutation throw
`PostedJournalMutation` after the entry status is `posted`.

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/Finance/JournalModelTest.php`

Expected: FAIL because models do not exist.

- [ ] **Step 3: Implement focused models**

All aggregate roots use `OwnsUserData`; child lines resolve ownership through
their entry and are never queried from request code without the parent relation.
Register model event guards for updating and deleting posted entries and for
updating/deleting lines belonging to posted entries.

```php
static::updating(function (JournalEntry $entry): void {
    if ($entry->getOriginal('status') === 'posted') {
        throw new PostedJournalMutation();
    }
});
```

Keep server-managed identifiers, hashes, status, and ownership out of `$fillable`.

- [ ] **Step 4: Run tests and static style checks**

Run: `cd backend && php artisan test tests/Feature/Finance/JournalModelTest.php && vendor/bin/pint --test app/Models app/Domain/Finance/Exceptions`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Models backend/app/Domain/Finance/Exceptions backend/tests/Feature/Finance/JournalModelTest.php
git commit -m "feat(finance): add immutable journal models"
```

### Task 5: Provision versioned system accounts and tax codes

**Files:**
- Create: `backend/config/finance_accounts.php`
- Create: `backend/app/Services/Finance/ProvisionFinanceLedger.php`
- Create: `backend/tests/Feature/Finance/ProvisionFinanceLedgerTest.php`

**Interfaces:**
- Consumes: `User`, `FinanceAccount`, and `FinanceTaxCode`.
- Produces: `ProvisionFinanceLedger::forUser(User $user): void` and stable system codes `BANK`, `AR`, `AP`, `REVENUE`, `EXPENSE`, `VAT_OUT`, `VAT_IN`, `CLEARING`.

- [ ] **Step 1: Write failing provisioning tests**

Assert one call creates the exact configured accounts and tax codes, a second
call changes no counts, and two users receive independent rows. Assert used
system accounts cannot be deleted or renumbered.

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/Finance/ProvisionFinanceLedgerTest.php`

Expected: FAIL because the provisioner does not exist.

- [ ] **Step 3: Implement deterministic upserts**

Define configuration arrays with stable codes and types. In one transaction,
use owner-scoped `updateOrCreate` keyed by system code. Do not overwrite a
user-facing name after initial creation. Create tax codes `NONE`, `DE_STD_19`,
and `DE_RED_7` with rates in basis points and links to the provisioned VAT
accounts.

- [ ] **Step 4: Run focused tests**

Run: `cd backend && php artisan test tests/Feature/Finance/ProvisionFinanceLedgerTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/config/finance_accounts.php backend/app/Services/Finance/ProvisionFinanceLedger.php backend/tests/Feature/Finance/ProvisionFinanceLedgerTest.php
git commit -m "feat(finance): provision journal accounts"
```

### Task 6: Implement the atomic journal posting service

**Files:**
- Create: `backend/app/Domain/Finance/PostingRequest.php`
- Create: `backend/app/Domain/Finance/PostedJournal.php`
- Create: `backend/app/Domain/Finance/Exceptions/UnbalancedJournal.php`
- Create: `backend/app/Services/Finance/PostJournalEntry.php`
- Create: `backend/tests/Feature/Finance/PostJournalEntryTest.php`

**Interfaces:**
- Consumes: `PostingRequest` containing user, source, kind, dates, description, currency, and `list<JournalLineData>`.
- Produces: `PostJournalEntry::handle(PostingRequest $request): PostedJournal`.

- [ ] **Step 1: Write failing posting tests**

Cover balanced creation, rejection of unequal base totals, rejection of mixed
owners/accounts, idempotent retry returning the original entry, concurrent-safe
journal numbering, and transaction rollback when a line fails.

```php
$posted = app(PostJournalEntry::class)->handle($request);
$this->assertSame(11900, $posted->entry->lines->where('side', 'debit')->sum('base_amount_minor'));
$this->assertSame(11900, $posted->entry->lines->where('side', 'credit')->sum('base_amount_minor'));
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/Finance/PostJournalEntryTest.php`

Expected: FAIL because the service does not exist.

- [ ] **Step 3: Implement one transactional use-case**

Inside `DB::transaction`, validate owner and balance, acquire a per-user/year
numbering lock, create the event in draft state, create entry and lines, compute
a canonical SHA-256 content hash, then mark event and entry posted. Catch the
unique source-event collision and load the existing result as an idempotent
success. Never catch `UnbalancedJournal`.

- [ ] **Step 4: Run tests repeatedly**

Run the following command three times:
`cd backend && php artisan test tests/Feature/Finance/PostJournalEntryTest.php`

Expected: PASS on every repetition.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Finance backend/app/Services/Finance/PostJournalEntry.php backend/tests/Feature/Finance/PostJournalEntryTest.php
git commit -m "feat(finance): post balanced journal entries"
```

### Task 7: Translate finalized EUR invoices into journal entries

**Files:**
- Create: `backend/app/Services/Finance/PostFinalizedInvoice.php`
- Create: `backend/tests/Feature/Finance/InvoiceJournalPostingTest.php`
- Modify: `backend/app/Http/Controllers/FinanceController.php`

**Interfaces:**
- Consumes: finalized `Invoice` and services from Tasks 5–6.
- Produces: `PostFinalizedInvoice::handle(Invoice $invoice): PostedJournal`.

- [ ] **Step 1: Write failing invoice posting tests**

Finalize a 19-percent EUR invoice and assert these base-minor lines:

```text
Debit  AR       11900
Credit REVENUE  10000
Credit VAT_OUT   1900
```

Also cover 7 percent, zero VAT, mixed line VAT rates, credit note sign handling,
owner isolation, duplicate finalize retry, and atomic rollback if posting fails.

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/Finance/InvoiceJournalPostingTest.php`

Expected: FAIL because finalization does not create journal rows.

- [ ] **Step 3: Implement the translator**

Read authoritative finalized totals and line VAT rates using decimal strings.
Group net and VAT by rate, resolve the provisioned accounts/tax codes, construct
`PostingRequest`, and delegate to `PostJournalEntry`. Reject non-EUR invoices in
this first slice with a typed domain exception before changing invoice state.

- [ ] **Step 4: Integrate inside the existing finalization transaction**

Inject `PostFinalizedInvoice` into the finalization action and call it after
number/totals are fixed but before commit. Do not calculate journal lines in the
controller. Preserve the existing JSON response.

- [ ] **Step 5: Run focused and regression tests**

Run: `cd backend && php artisan test tests/Feature/Finance/InvoiceJournalPostingTest.php tests/Feature/Finance/InvoiceFinalizationCharacterizationTest.php tests/Feature/InvoiceStornoTest.php tests/Feature/FinanceReportsTest.php tests/Feature/TaxReportsTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/Finance/PostFinalizedInvoice.php backend/app/Http/Controllers/FinanceController.php backend/tests/Feature/Finance/InvoiceJournalPostingTest.php
git commit -m "feat(finance): journal finalized invoices"
```

### Task 8: Expose a read-only paginated journal API

**Files:**
- Create: `backend/app/Http/Controllers/Finance/JournalController.php`
- Create: `backend/app/Http/Resources/Finance/JournalEntryResource.php`
- Create: `backend/tests/Feature/Finance/JournalApiTest.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Consumes: posted `JournalEntry` rows.
- Produces: `GET /api/v1/finance/journal?year=&cursor=` with cursor pagination.

- [ ] **Step 1: Write failing API tests**

Assert authentication, `module:finance`, owner isolation, stable newest-first
ordering, cursor pagination, optional year filtering, and absence of internal
content hashes. Attempt POST/PUT/DELETE and assert method-not-allowed.

- [ ] **Step 2: Verify failure**

Run: `cd backend && php artisan test tests/Feature/Finance/JournalApiTest.php`

Expected: FAIL with route not found.

- [ ] **Step 3: Implement resource and controller**

Use `JournalEntry::query()->with(['lines.account', 'event'])->where(...)`, a
maximum page size of 100, and `cursorPaginate`. Return integer minor units and
currency codes without converting to floats.

- [ ] **Step 4: Add the read-only route**

Register the GET route inside the existing authenticated `module:finance`
group. Add no mutation routes.

- [ ] **Step 5: Run API tests**

Run: `cd backend && php artisan test tests/Feature/Finance/JournalApiTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/Finance backend/app/Http/Resources/Finance backend/routes/api.php backend/tests/Feature/Finance/JournalApiTest.php
git commit -m "feat(finance): expose read-only journal API"
```

### Task 9: Verify the foundation and document the next boundary

**Files:**
- Modify: `docs/superpowers/plans/2026-08-27-finance-journal-foundation.md`
- Create: `docs/finance/journal-foundation.md`

**Interfaces:**
- Consumes: all previous tasks.
- Produces: verified foundation and operational documentation for migration work.

- [ ] **Step 1: Run finance and full backend tests**

Run: `cd backend && php artisan test --testsuite=Feature --filter='Finance|Invoice|Tax|Receipt|Payment'`

Expected: PASS.

Run: `cd backend && composer test`

Expected: PASS with no failed or risky tests.

- [ ] **Step 2: Run quality checks**

Run: `cd backend && vendor/bin/pint --test app/Domain/Finance app/Services/Finance app/Models app/Http/Controllers/Finance app/Http/Resources/Finance tests/Feature/Finance tests/Unit/Domain/Finance`

Run: `cd backend && vendor/bin/phpstan analyse app/Domain/Finance app/Services/Finance app/Models/FinanceAccount.php app/Models/FinanceTaxCode.php app/Models/FinanceEvent.php app/Models/JournalEntry.php app/Models/JournalLine.php --memory-limit=1G`

Expected: both exit zero.

- [ ] **Step 3: Document invariants and operations**

Document account provisioning, posting invariants, immutable correction policy,
API fields, supported EUR invoice scope, and the exact command set used for
verification. State explicitly that legacy data is not journalized until the
next migration plan runs.

- [ ] **Step 4: Update checklist and commit**

Mark completed checkboxes only after their commands pass.

```bash
git add docs/superpowers/plans/2026-08-27-finance-journal-foundation.md docs/finance/journal-foundation.md
git commit -m "docs(finance): document journal foundation"
```

## Follow-up plan series

After this foundation passes review, create and execute separate detailed plans
in this dependency order:

1. `finance-legacy-migration`: canonical receipts, resumable migration, and exact control totals.
2. `finance-reconciliation-rules`: bank clearing, posting rules, and canonical receipt matching.
3. `finance-open-items`: receivables/payables, allocations, partial payments, Skonto, and dunning.
4. `finance-datev`: account mapping validation, export batches, and period locks.
5. `finance-tax-fx-reports`: historical FX, journal-backed EÜR/VAT, and report reconciliation.
6. `finance-liquidity`: planned cash flows, recurring commitments, and plan/actual reporting.
7. `finance-payments-connectors`: incoming invoice workflow, SEPA XML, and bank adapter interface.
8. `finance-customer-portal`: quote acceptance, invoice delivery, and payment links.
9. `finance-frontend-decomposition`: routed pages, paginated stores, and removal of the legacy snapshot.
10. `finance-release`: full migration rehearsal, load/security/E2E testing, backup, and deployment runbook.
