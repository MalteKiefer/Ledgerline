# Invoice Task 9 Report

## Status

Implemented idempotent invoice delivery, explicit retry, immutable PDF transport, and owner-timezone aging/dunning. The implementation reuses the existing `CompanySmtpMailer` and its safe-pre-accept versus uncertain-outcome classification. `FinanceServiceProvider` now binds only the new `InvoiceMailer` port to `CompanyInvoiceMailer`; existing Project and Quote bindings were preserved.

## Delivered behavior

- Finalized invoices validate recipient, current immutable revision, PDF path/hash/bytes, and owner SMTP configuration before a delivery row or queue job is created.
- Delivery keys are stored only as SHA-256 hashes. Same key and payload replay the original delivery; changed payload returns `delivery_idempotency_conflict`.
- Delivery UUID and Message-ID remain stable for one attempt. Queued jobs contain only owner and numeric delivery identity and dispatch after commit.
- Workers lock series, invoice, revision, and delivery in a consistent order, re-read the immutable PDF, verify its digest, and transition `pending -> sending -> sent` with one `invoice.sent` activity.
- Safe pre-accept SMTP failures use at most three attempts with backoff `[60, 300, 1800]`; stored errors contain only stable redacted codes. Ambiguous transport results become `unknown` and are never automatically resent.
- A post-accept persistence crash leaves `sending`; the next worker invocation records `unknown` without invoking SMTP again. This regression was mutation-checked by temporarily removing the recovery transition and observing the test fail.
- Explicit retry creates one new idempotent bounded delivery while retaining the exact source revision, recipient, and immutable PDF. Retry also repeats PDF validation and reminder balance/due checks.
- Aging uses `UserSetting::timezone`, falling back to application timezone, and returns exact integer minor-unit remainders in 1–30, 31–60, and 61+ day buckets. Finalized-unsent, paid, future-due, zero/negative-open, cancelled/non-sent, and foreign-owner invoices are excluded.
- Reminder occurrence identity is derived from owner-scoped invoice plus reminder level, so repeated requests for one level produce one delivery/history path. A worker rechecks sent status, positive open amount, due date, current revision, and ownership under lock before SMTP. Successful reminder activity contains level and recipient domain, never the full recipient.
- The mail adapter attaches only digest-verified bytes from the immutable revision object and delegates owner SMTP setup, outbound-host policy, exception classification, and `finally` teardown to the hardened Quote transport stack.

## TDD evidence

Observed RED before implementation for the missing `InvoiceMailer`, `QueueInvoiceDelivery`, `CompanyInvoiceMailer`, `SendInvoiceDeliveryJob`, `RetryInvoiceDelivery`, `InvoiceAgingQuery`, `QueueInvoiceReminder`, provider binding, stable UUID DTO exposure, missing PDF-object preflight, and worker balance recheck. Each was followed by the smallest implementation and a focused GREEN run.

Important regression evidence:

- Post-accept recovery mutation: removing `sending -> unknown` caused `test_persistence_failure_after_transport_acceptance_becomes_unknown_before_any_resend` to fail with the row remaining `sending`; restoring it returned GREEN.
- Paid-before-send reminder: initial test showed one SMTP call; the locked worker guard reduced this to zero and persisted `invoice_not_overdue`.

## Verification

- Task 9 plus provider, legacy invoice mail/dunning, and Quote SMTP regression suite: 54 tests, 53 passed, 296 assertions, 1 environment-dependent skip.
- Task 9 focused delivery/dunning suite: 15 tests, all passed.
- Task-scoped Pint: passed.
- Task-scoped PHPStan with 1 GB: passed with zero findings.
- Whole `app/Modules/Finance` PHPStan has two pre-existing Project iterable-value findings in `ReorderWorkItems::handle()` and `ListProjectWork::handle()`; no Task 9 finding remains.
- A broad 577-test FinanceModule run reached 560 passes / 3507 assertions / 14 skips but had three unrelated active Project Task 8 failures (one unconfigured legacy S3 test disk and two Project schema expectations). The first broad attempt also exposed the existing Dompdf 128 MB limit; direct PHPUnit with 1 GB passed the Invoice PDF portion and progressed to those unrelated Project failures.

## Scope and operational notes

- No migration, route, controller, OpenAPI, push, tag, or deployment change was made for Task 9.
- No recipient address, SMTP secret, PDF path, or PDF bytes are serialized into jobs or written into activity payloads.
- Production requires a queue worker for `SendInvoiceDeliveryJob`; synchronous development/test queues still honor `afterCommit`.

## Review round 1 hardening (2026-08-29)

All requested review findings were implemented with focused RED/GREEN regressions:

- Exact same-key/same-payload send, retry, and reminder replays now resolve the original delivery before invoice state, PDF, SMTP, balance, due-date, or aging preflight. A changed recipient or reminder level still produces `delivery_idempotency_conflict`.
- A delivery row committed as `pending` with zero attempts is a durable dispatch checkpoint. If dispatch throws before the worker starts, exact replay dispatches that same delivery again; no second delivery or Message-ID is created. Queue uniqueness plus the worker execution lease prevent duplicate accepted transport execution.
- Reminder retries copy the immutable reminder level from the source `invoice.reminder.queued` activity, append a new queued activity linked to the retry, and complete with exactly one level-preserving `invoice.reminder.sent` activity.
- Repository writes now lock in the common order document series, invoice, revision, delivery. Reminder due date, positive balance, sent status, and owner timezone are rechecked inside the queue/retry transaction using the command's exact `asOf` instant.
- Workers use an owner-and-delivery scoped five-minute cache lease. An overlapping worker leaves an active `sending` attempt untouched; after the lease is gone, a persisted `sending` row is treated as stale/uncertain without another SMTP call.
- Aging boundaries are covered exactly at 30, 31, 60, and 61 owner-local calendar days.
- An isolated PostgreSQL concurrency test creates two simultaneous same-key delivery workers behind a barrier and asserts one delivery, one creator, and one replay. It safely skips unless `pdo_pgsql` and `FINANCE_TEST_PGSQL_URL` are available.

Review verification:

- Delivery, dunning, provider, invoice PDF, and reused Quote SMTP suite with 1 GB: 59 tests, 56 passed, 336 assertions, 3 environment-dependent skips.
- Focused delivery/dunning/provider suite: 25 tests, 24 passed, 124 assertions, 1 PostgreSQL skip.
- Task-scoped Pint: passed.
- Production Task 9 PHPStan scope: passed with zero findings.
- The default 128 MB combined suite still exhausts memory in Dompdf; the identical suite passes with a 1 GB PHP memory limit.
- `FinanceServiceProvider` was preserved unchanged in this review commit. No push, tag, or deployment was performed.
