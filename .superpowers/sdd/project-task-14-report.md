# Project Plan Task 14 — complete verification and downstream handoff report

Date: 2026-08-30

## Outcome

Ran every verification command the plan specifies, recorded the results in a
new "Verification record" and "Downstream handoff" section of
`docs/finance/projects-documents-notes.md`, and marked the Task 12–14
checkboxes done in the plan file itself. No code changes in this task —
verification and documentation only.

## Environment note: `php artisan test` vs `vendor/bin/phpunit`

On this machine, `php artisan test` re-execs a child PHP process that does
not inherit the parent's `-d memory_limit` CLI flag and is capped at the
shared `php.ini` `memory_limit` of 128M. That is too low for
`InvoicePdfTest`'s dompdf font rendering, which fatals with an out-of-memory
error unrelated to any Finance Projects code. Running `vendor/bin/phpunit`
directly (with `php -d memory_limit=1024M vendor/bin/phpunit ...`) honors the
flag and passes. All backend runs in this task use that direct invocation;
this is recorded in the docs so a future run on this machine does not
mistake the artisan-test memory failure for a real regression.

## Results

- Focused backend suites (`FinanceModule`, `Unit/Modules/Finance`,
  `FinanceProjectPlanTest`, `FinanceRelationalTest`, `FinanceScopeTest`,
  `FinanceQuoteTest`, `FilesRelationalTest`, `NotesFeatureTest`,
  `Guards/ApiSurfaceGuardTest`): **957 tests, 5950 assertions, PASS**.
- `vendor/bin/pint --test` on `app/Modules/Finance`,
  `tests/Feature/FinanceModule`, `tests/Unit/Modules/Finance`: **PASS**.
- `vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G`: **0 errors**.
- Frontend: `npm run test:js` (31 files, 409 tests), `npm run typecheck`
  (`vue-tsc --noEmit`), `npm run lint` (`eslint src`), `npm run build` — all
  **PASS**.
- Full backend suite (`vendor/bin/phpunit`, no filter): **2367 tests, 11487
  assertions, 34 skipped, 1 risky, 3 failed.**

## The three full-suite failures

All three are outside this plan's changes. Proof: `git diff --stat
a6455fac..HEAD -- <file>` is **empty** for every file each failure or its
production code touches, where `a6455fac` is the commit Task 12's work
started from (the shared checkpoint commit named in this branch's mission).
Since the exact bytes are unchanged and each test's only time input is a
fixed `DateTimeImmutable` (not real wall-clock time), the same failure
necessarily reproduces at the base commit too.

1. `BinaryProcessTest::test_run_returns_stdout_on_success` — a generic
   process-execution helper test expects `\n` but observes `\r\n` from a
   shelled-out command on this Windows host. Line-ending environment
   mismatch, nothing to do with Finance.
2. `InvoiceDunningTest::test_overdue_reminder_is_idempotent_per_level_and_records_one_successful_history_entry` —
   expects `daysOverdue = 28`, observes `29`, in the pre-existing (non-Projects)
   invoice dunning/reminder feature. Tasks 12–13 touched zero files this test
   or its production code depends on.
3. `MailOriginWriteTest::test_delete_after_import_removes_origin_uids` — Mail
   module, unrelated to Finance entirely.

None was silently skipped or waved away: each is named, diagnosed, and its
pre-existing status is proven rather than assumed, per the plan's Step 4
instruction.

## Downstream handoff (recorded in docs/finance/projects-documents-notes.md)

- Quote integration: `ProjectFromQuoteTarget` ready; the quote plan must
  still resolve its single `converted`-state ambiguity before enabling
  quote-to-project UI.
- Invoices/payments: `ProjectToInvoicePort` ready; the invoice/payments
  rewrite replaces `LegacyInvoiceDraftFromTimeAdapter` with a modular
  adapter behind the same port.
- Global legacy migration: calls `LegacyProjectMapper` (Task 13), which
  performs no writes itself.
- Frontend cutover: mounts `frontend/src/modules/finance/projects/routes.ts`
  (Task 12) and switches the canonical API alias.
- Legacy removal: deferred to `finance-legacy-removal`, per cutover gate 9.

## Scope hygiene

- Only `docs/finance/projects-documents-notes.md` and
  `docs/superpowers/plans/2026-08-28-finance-projects-documents-notes.md`
  (checkbox marks for Tasks 12–14 only, leaving Tasks 1–11's checkboxes as
  they already stood) were modified.
- No push, tag, deployment, or integration action was performed.
