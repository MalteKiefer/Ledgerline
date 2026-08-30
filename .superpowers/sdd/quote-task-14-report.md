# Quote Task 14 Report

## Status

Ran the plan's complete verification pass across the modular quote workflow
(Tasks 1-13) and recorded the results in `docs/finance/quotes-workflow.md`.
No source change was needed for this task — it is verification and
record-keeping only, as the plan specifies.

## Verification

All commands and their exact results are recorded in the "Verification
record" section of `docs/finance/quotes-workflow.md` (dated 2026-08-30). Summary:

- Focused backend suites (`FinanceModule`, `Unit/Modules/Finance`,
  `FinanceQuoteTest`, `FinanceProjectPlanTest`, `InvoiceEmailTest`,
  `InvoiceVersionPdfTest`, `Guards/ApiSurfaceGuardTest`): **908 tests, 888
  passed, 1 failed** (pre-existing, unrelated — see below), 19 skipped.
- `vendor/bin/pint --test app/Modules/Finance tests/Feature/FinanceModule tests/Unit/Modules/Finance`: **passed.**
- `vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G`: **0 errors.**
- Frontend: `vitest run` **398/402 passed** (4 pre-existing, unrelated
  Projects-module failures — see below); `vue-tsc --noEmit` clean except the
  same two Projects-module test files; `eslint src` **0 errors**; `vite build`
  **succeeded.**
- Full backend suite (`php artisan test`, run as `vendor/bin/phpunit` directly
  — see environment note below): **2370 tests, 2332 passed, 11 failed, 5
  errors, 22 skipped, 1 risky.** Every failure/error is outside
  `app/Modules/Finance` and was spot-verified to reproduce identically on
  `c6c2d7c8` (the commit immediately preceding this plan's Task 12-14 work):
  Windows GPG/mail-key tooling gaps (`MailKeyTest`, `KeyServerControllerTest`,
  `FilesCryptoTest`, `MailOriginWriteTest`), Windows `tar` incompatibility
  (`FilesArchiveTest`, `Backup\InvoiceBlobSourceTest`), a CRLF-vs-LF assertion
  (`Unit\Support\BinaryProcessTest`), and one invoice-dunning count mismatch
  (`InvoiceDunningTest`).

## Pre-existing failures — proof of reproduction on the base commit

Two categories of failure appear in every run above and are not caused by
this plan:

1. **`InvoiceDunningTest::test_overdue_reminder_is_idempotent_per_level_and_records_one_successful_history_entry`**
   (`Failed asserting that 29 is identical to 28`). Verified in isolation on
   `codex/finance-quotes-cutover` and, after `git checkout c6c2d7c8`, on that
   base commit too — byte-identical failure both times.
2. **Windows-tooling failures**: `Unit\Support\BinaryProcessTest::test_run_returns_stdout_on_success`
   (CRLF vs LF), `FilesArchiveTest::test_tar_gz_round_trips` and
   `Backup\InvoiceBlobSourceTest::test_it_archives_the_invoices_prefix`
   (`/usr/bin/tar: Cannot connect to C: resolve failed` — Windows `tar`
   misparsing a `C:` path as an `scp`-style host), and
   `MailKeyTest::test_generate_pgp_rsa` / `test_generate_pgp_ecc_multiple_identities`
   / `test_pgp_decrypt_end_to_end` / `test_lazy_decrypt_on_read_after_key_added`
   / `test_import_pgp_captures_identity_and_algorithm_metadata` (missing/failing
   GPG homedir setup on this machine). Verified `BinaryProcessTest` and
   `MailKeyTest` reproduce identically on `c6c2d7c8`. `KeyServerControllerTest`,
   `FilesCryptoTest`, and `MailOriginWriteTest` fail the same GPG-dependent way
   and were not independently re-run on the base commit, since they share the
   exact same GPG-homedir root cause already confirmed reproducible.

Both `git checkout c6c2d7c8` / `git checkout codex/finance-quotes-cutover`
round-trips left the working tree clean (verified with `git status --short`
before and after); no other worktree, branch, or directory was touched.

## Environment note

This machine's default PHP `memory_limit` (128M) is too low for Dompdf's
font-metrics pass in `InvoicePdfTest` and similar renderer tests. `php artisan
test` shells out to a fresh `vendor/bin/phpunit` process that does not inherit
the outer process's `-d` flags, so every full-suite command in this report ran
as `php -d memory_limit=1024M vendor/bin/phpunit <args>` instead of
`php artisan test <args>` — the same test runner and `phpunit.xml`, invoked
without the wrapper that silently drops the ini override.

## Scope and integration

This task changed only `docs/finance/quotes-workflow.md` (verification record)
and the Task 12-14 checkboxes in
`docs/superpowers/plans/2026-08-28-finance-quotes-workflow.md`. Tasks 1-11's
checkboxes were left as the previous sessions left them (unchecked, with their
completion recorded instead in `.superpowers/sdd/quote-task-{2..11}-report.md`);
retroactively marking work this session did not itself re-verify was out of scope.

## Concerns

None new. The Task 11 report's open concern (OpenAPI still declaring Quote
money fields as `integer/int64` instead of decimal strings) remains open for
a later task — nothing in Tasks 12-14 touches `openapi.yaml`.
