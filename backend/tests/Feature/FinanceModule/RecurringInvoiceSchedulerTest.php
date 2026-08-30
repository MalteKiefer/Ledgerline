<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Recurring\ClaimDueRecurringInvoiceRuns;
use App\Modules\Finance\Application\Commands\Recurring\CreateRecurringInvoiceTemplate;
use App\Modules\Finance\Application\Commands\Recurring\PauseRecurringInvoiceTemplate;
use App\Modules\Finance\Application\Commands\Recurring\ProcessRecurringInvoiceRun;
use App\Modules\Finance\Application\Commands\Recurring\RetryRecurringInvoiceRun;
use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateView;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Domain\Recurring\RecurrenceInterval;
use App\Modules\Finance\Domain\Shared\Discount;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class RecurringInvoiceSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ProcessRecurringInvoiceRun is resolved through the container (it is a
        // queued job dependency), so it eagerly builds FinalizeInvoice's document
        // renderer/storage even for a `draft`-mode run that never reaches
        // finalization. Bind harmless in-memory fakes everywhere so no test needs
        // the real (S3-backed, unconfigured in testing) storage disk; individual
        // tests still override these with failure-injecting variants.
        $this->app->instance(DocumentRenderer::class, new SchedulerTestRenderer);
        $this->app->instance(DocumentStorage::class, new SchedulerTestStorage);
        $this->app->instance(InvoiceMailer::class, new SchedulerAutoSendMailer);
    }

    public function test_claiming_the_same_instant_twice_creates_exactly_one_run(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $now = new DateTimeImmutable('2026-01-31T00:00:00+00:00');
        $this->app->instance(Clock::class, $this->clockAt($now));
        $this->createTemplate('draft', new DateTimeImmutable('2026-01-31'), null);

        $first = app(ClaimDueRecurringInvoiceRuns::class)->handle($now);
        $second = app(ClaimDueRecurringInvoiceRuns::class)->handle($now);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertDatabaseCount('finance_recurring_invoice_runs', 1);
    }

    public function test_one_tick_claims_at_most_one_hundred_per_template_and_three_ticks_catch_up_without_gaps(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $start = new DateTimeImmutable('2006-01-15T00:00:00+00:00');
        $this->app->instance(Clock::class, $this->clockAt($start));
        $created = $this->createTemplate('draft', $start, null);
        // 250 due monthly occurrences: steps 0..249, the 250th at start + 249 months.
        $asOf = new DateTimeImmutable('2026-10-15T00:00:00+00:00');
        $this->app->instance(Clock::class, $this->clockAt($asOf));

        $tick1 = app(ClaimDueRecurringInvoiceRuns::class)->handle($asOf);
        $tick2 = app(ClaimDueRecurringInvoiceRuns::class)->handle($asOf);
        $tick3 = app(ClaimDueRecurringInvoiceRuns::class)->handle($asOf);
        $tick4 = app(ClaimDueRecurringInvoiceRuns::class)->handle($asOf);

        $this->assertCount(100, $tick1);
        $this->assertCount(100, $tick2);
        $this->assertCount(50, $tick3);
        $this->assertCount(0, $tick4);
        $this->assertDatabaseCount('finance_recurring_invoice_runs', 250);
        $distinct = DB::table('finance_recurring_invoice_runs')
            ->where('template_id', $created->id->value)
            ->distinct()
            ->count('scheduled_for');
        $this->assertSame(250, $distinct);
        $this->assertSame(
            '2026-11-15 00:00:00.000000',
            DB::table('finance_recurring_invoice_templates')->where('id', $created->id->value)->value('next_run_at'),
        );
    }

    public function test_paused_template_claims_nothing_and_a_completed_template_stops_after_its_end_date(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $at = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
        $this->app->instance(Clock::class, $this->clockAt($at));

        $paused = $this->createTemplate('draft', $at, null);
        app(PauseRecurringInvoiceTemplate::class)->handle($paused->id, 0, new IdempotencyKey('pause-scheduler-test'));
        $single = $this->createTemplate('draft', $at, $at);

        $claimed = app(ClaimDueRecurringInvoiceRuns::class)->handle($at);

        $this->assertCount(1, $claimed);
        $this->assertSame($single->id->value, DB::table('finance_recurring_invoice_runs')->value('template_id'));
        $this->assertSame(
            'completed',
            DB::table('finance_recurring_invoice_templates')->where('id', $single->id->value)->value('status'),
        );
        $this->assertNull(
            DB::table('finance_recurring_invoice_templates')->where('id', $single->id->value)->value('next_run_at'),
        );

        $second = app(ClaimDueRecurringInvoiceRuns::class)->handle($at);
        $this->assertCount(0, $second);
    }

    public function test_draft_mode_run_creates_exactly_one_draft_invoice_and_stays_terminal_on_every_sweep(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $at = new DateTimeImmutable('2026-04-30T08:00:00+00:00');
        $this->app->instance(Clock::class, $this->clockAt($at));
        $this->createTemplate('draft', new DateTimeImmutable('2026-04-30'), null, '08:00:00');

        Artisan::call('finance:run-recurring-invoices');
        Artisan::call('finance:run-recurring-invoices');

        $this->assertDatabaseCount('finance_recurring_invoice_runs', 1);
        $run = (array) DB::table('finance_recurring_invoice_runs')->first();
        $this->assertSame('draft_created', $run['status']);
        $this->assertSame('draft_created', $run['last_completed_step']);
        $this->assertIsInt($run['invoice_id']);
        $this->assertDatabaseCount('finance_invoices', 1);
        $this->assertSame('draft', DB::table('finance_invoices')->where('id', $run['invoice_id'])->value('workflow_status'));
    }

    public function test_auto_send_mode_progresses_from_draft_through_finalized_delivery_to_sent(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $at = new DateTimeImmutable('2026-05-31T08:00:00+00:00');
        $renderer = new SchedulerTestRenderer;
        $storage = new SchedulerTestStorage;
        $mailer = new SchedulerAutoSendMailer;
        $this->app->instance(Clock::class, $this->clockAt($at));
        $this->app->instance(DocumentRenderer::class, $renderer);
        $this->app->instance(DocumentStorage::class, $storage);
        $this->app->instance(InvoiceMailer::class, $mailer);
        $this->createTemplate('auto_send', new DateTimeImmutable('2026-05-31'), null, '08:00:00');

        Artisan::call('finance:run-recurring-invoices');

        $run = (array) DB::table('finance_recurring_invoice_runs')->first();
        $this->assertSame('sent', $run['status']);
        $this->assertSame('sent', $run['last_completed_step']);
        $invoice = (array) DB::table('finance_invoices')->where('id', $run['invoice_id'])->first();
        $this->assertSame('sent', $invoice['workflow_status']);
        $this->assertNotNull($invoice['number']);
        $this->assertSame(1, $renderer->calls);
        $this->assertSame(1, count($mailer->dispatched));
    }

    public function test_a_step_failure_marks_the_run_failed_and_retry_resumes_without_creating_a_second_invoice(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $at = new DateTimeImmutable('2026-06-30T08:00:00+00:00');
        $renderer = new SchedulerTestRenderer(failFirstCalls: 1);
        $storage = new SchedulerTestStorage;
        $mailer = new SchedulerAutoSendMailer;
        $this->app->instance(Clock::class, $this->clockAt($at));
        $this->app->instance(DocumentRenderer::class, $renderer);
        $this->app->instance(DocumentStorage::class, $storage);
        $this->app->instance(InvoiceMailer::class, $mailer);
        $this->createTemplate('auto_send', new DateTimeImmutable('2026-06-30'), null, '08:00:00');

        Artisan::call('finance:run-recurring-invoices');

        $failed = (array) DB::table('finance_recurring_invoice_runs')->first();
        $this->assertSame('failed', $failed['status']);
        $this->assertSame('draft_created', $failed['last_completed_step']);
        $this->assertNotNull($failed['last_error_code']);
        $this->assertDatabaseCount('finance_invoices', 1);
        $this->assertSame('draft', DB::table('finance_invoices')->where('id', $failed['invoice_id'])->value('workflow_status'));

        app(RetryRecurringInvoiceRun::class)->handle(new RecurringRunId((int) $failed['id']));
        app(ProcessRecurringInvoiceRun::class)->handle(new RecurringRunId((int) $failed['id']));

        $recovered = (array) DB::table('finance_recurring_invoice_runs')->first();
        $this->assertSame('sent', $recovered['status']);
        $this->assertDatabaseCount('finance_invoices', 1);
        $this->assertSame($failed['invoice_id'], $recovered['invoice_id']);
    }

    public function test_a_mail_dispatch_failure_retries_only_the_delivery_step_never_the_finalized_revision(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $at = new DateTimeImmutable('2026-07-31T08:00:00+00:00');
        $renderer = new SchedulerTestRenderer;
        $storage = new SchedulerTestStorage;
        $mailer = new SchedulerAutoSendMailer(dispatchFailuresRemaining: 1);
        $this->app->instance(Clock::class, $this->clockAt($at));
        $this->app->instance(DocumentRenderer::class, $renderer);
        $this->app->instance(DocumentStorage::class, $storage);
        $this->app->instance(InvoiceMailer::class, $mailer);
        $this->createTemplate('auto_send', new DateTimeImmutable('2026-07-31'), null, '08:00:00');

        Artisan::call('finance:run-recurring-invoices');
        $afterFailure = (array) DB::table('finance_recurring_invoice_runs')->first();
        $this->assertSame('failed', $afterFailure['status']);
        $this->assertSame('finalized', $afterFailure['last_completed_step']);
        $this->assertSame(1, $renderer->calls);

        app(RetryRecurringInvoiceRun::class)->handle(new RecurringRunId((int) $afterFailure['id']));
        app(ProcessRecurringInvoiceRun::class)->handle(new RecurringRunId((int) $afterFailure['id']));

        $sent = (array) DB::table('finance_recurring_invoice_runs')->first();
        $this->assertSame('sent', $sent['status']);
        $this->assertSame(1, $renderer->calls);
        $this->assertSame(2, count($mailer->dispatched));
    }

    private function clockAt(DateTimeImmutable $at): Clock
    {
        return new readonly class($at) implements Clock
        {
            public function __construct(private DateTimeImmutable $at) {}

            public function now(): DateTimeImmutable
            {
                return $this->at;
            }
        };
    }

    private function createTemplate(
        string $mode,
        DateTimeImmutable $start,
        ?DateTimeImmutable $end,
        string $runTime = '00:00:00',
    ): RecurringTemplateView {
        return app(CreateRecurringInvoiceTemplate::class)->handle(
            new RecurringTemplateData(
                mode: $mode,
                interval: RecurrenceInterval::Monthly,
                timezone: 'UTC',
                startDate: $start,
                endDate: $end,
                runTime: $runTime,
                initialVersion: new RecurringTemplateVersionData($start, $this->draft()),
            ),
            new IdempotencyKey('scheduler-create-'.bin2hex(random_bytes(6))),
        );
    }

    private function draft(): InvoiceDraftData
    {
        return new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2020-01-01'),
            dueDate: new DateTimeImmutable('2020-01-15'),
            currency: 'EUR',
            customer: ['name' => 'ACME', 'email' => 'billing@acme.test'],
            lines: [new InvoiceLineData('Retainer', '1.0000', 10_000, 1_900, 'mo', null, 'service')],
            discount: Discount::none('EUR'),
            controlNetMinor: 10_000,
            controlVatMinor: 1_900,
            controlGrossMinor: 11_900,
        );
    }
}

final class SchedulerTestRenderer implements DocumentRenderer
{
    public int $calls = 0;

    public function __construct(private int $failFirstCalls = 0) {}

    /** @param array<array-key, mixed> $snapshot */
    public function render(array $snapshot): string
    {
        $this->calls++;
        if ($this->failFirstCalls > 0) {
            $this->failFirstCalls--;
            throw new RuntimeException('injected_recurring_render_failure');
        }

        return '%PDF-scheduler-'.json_encode($snapshot, JSON_THROW_ON_ERROR);
    }
}

final class SchedulerTestStorage implements DocumentStorage
{
    /** @var array<string, string> */
    public array $documents = [];

    public function putPdf(string $seriesUuid, string $bytes, DocumentStorageWrite $write): StoredDocument
    {
        $sha256 = hash('sha256', $bytes);
        $path = 'finance/revisions/'.substr($sha256, 0, 2).'/'.$sha256.'.pdf';
        $this->documents[$path] = $bytes;

        return new StoredDocument($path, $sha256);
    }

    public function delete(DocumentStorageWrite $write): void {}
}

final class SchedulerAutoSendMailer implements InvoiceMailer
{
    /** @var list<array{int, int}> */
    public array $dispatched = [];

    public function __construct(private int $dispatchFailuresRemaining = 0) {}

    public function assertConfigured(int $ownerId): void {}

    public function assertDocumentReady(string $path, string $sha256): void {}

    public function dispatch(int $ownerId, DeliveryId $deliveryId): void
    {
        $this->dispatched[] = [$ownerId, $deliveryId->value];
        if ($this->dispatchFailuresRemaining > 0) {
            $this->dispatchFailuresRemaining--;

            throw new RuntimeException('injected_recurring_dispatch_failure');
        }

        app(InvoiceRepository::class)->markDeliverySent($deliveryId, new DateTimeImmutable);
    }
}
