<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RecurringInvoiceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_exposes_versioned_templates_claimable_runs_and_execution_context(): void
    {
        $requiredColumns = [
            'finance_recurring_invoice_templates' => [
                'id', 'user_id', 'uuid', 'mode', 'interval', 'timezone', 'start_date',
                'end_date', 'run_time', 'anchor_day', 'month_end_anchor', 'next_run_at',
                'status', 'paused_at', 'current_version_id', 'version', 'created_at', 'updated_at',
            ],
            'finance_recurring_invoice_template_versions' => [
                'id', 'user_id', 'template_id', 'version_number', 'effective_from',
                'draft_snapshot', 'snapshot_sha256', 'created_by', 'created_at',
            ],
            'finance_recurring_invoice_runs' => [
                'id', 'user_id', 'uuid', 'template_id', 'template_version_id',
                'scheduled_for', 'scheduled_local_date', 'status', 'last_completed_step',
                'invoice_id', 'delivery_id', 'attempts', 'idempotency_key_hash',
                'claim_token_hash', 'claimed_at', 'claim_expires_at', 'next_retry_at',
                'last_error_code', 'last_error_detail', 'created_at', 'updated_at',
            ],
        ];

        foreach ($requiredColumns as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
            $this->assertTrue(Schema::hasColumns($table, $columns));
        }

        $this->assertFalse(Schema::hasColumn(
            'finance_recurring_invoice_template_versions',
            'updated_at',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_recurring_invoice_template_versions',
            ['user_id', 'template_id', 'version_number'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_recurring_invoice_template_versions',
            ['user_id', 'template_id', 'effective_from'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_recurring_invoice_runs',
            ['template_id', 'scheduled_for'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_recurring_invoice_runs',
            ['user_id', 'idempotency_key_hash'],
            'unique',
        ));
    }

    public function test_templates_require_exact_schedule_pause_and_optimistic_version_values(): void
    {
        $owner = User::factory()->create();
        $templateId = $this->insertTemplate((int) $owner->id, 1);

        $this->assertGreaterThan(0, $templateId);

        $suffix = 10;
        foreach ([
            'mode' => ['mode' => 'automatic'],
            'interval' => ['interval' => 'weekly'],
            'timezone' => ['timezone' => ''],
            'run time' => ['run_time' => '25:00:00'],
            'anchor zero' => ['anchor_day' => 0],
            'anchor high' => ['anchor_day' => 32],
            'end before start' => ['end_date' => '2025-12-31'],
            'status' => ['status' => 'disabled'],
            'paused without timestamp' => ['status' => 'paused', 'paused_at' => null],
            'active with pause timestamp' => ['status' => 'active', 'paused_at' => now()],
            'completed with next run' => ['status' => 'completed', 'next_run_at' => '2026-09-01 06:00:00'],
            'active without next run' => ['status' => 'active', 'next_run_at' => null],
            'negative version' => ['version' => -1],
        ] as $label => $invalid) {
            $this->expectConstraintViolation(fn (): int => $this->insertTemplate(
                (int) $owner->id,
                $suffix++,
                $invalid,
            ), $label);
        }

        $pausedId = $this->insertTemplate((int) $owner->id, 30, [
            'status' => 'paused',
            'paused_at' => now(),
        ]);
        $completedId = $this->insertTemplate((int) $owner->id, 31, [
            'status' => 'completed',
            'next_run_at' => null,
        ]);

        $this->assertGreaterThan(0, $pausedId);
        $this->assertGreaterThan(0, $completedId);
    }

    public function test_template_versions_are_positive_effective_dated_unique_and_immutable(): void
    {
        $owner = User::factory()->create();
        $templateId = $this->insertTemplate((int) $owner->id, 40);
        $versionId = $this->insertTemplateVersion((int) $owner->id, $templateId, 1);

        $this->expectConstraintViolation(fn (): int => $this->insertTemplateVersion(
            (int) $owner->id,
            $templateId,
            0,
            ['effective_from' => '2026-09-01'],
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertTemplateVersion(
            (int) $owner->id,
            $templateId,
            1,
            ['effective_from' => '2026-09-01'],
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertTemplateVersion(
            (int) $owner->id,
            $templateId,
            2,
            ['effective_from' => '2026-08-28'],
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertTemplateVersion(
            (int) $owner->id,
            $templateId,
            2,
            [
                'effective_from' => '2026-09-01',
                'snapshot_sha256' => str_repeat('z', 64),
            ],
        ));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_template_versions')
            ->where('id', $versionId)
            ->update(['draft_snapshot' => '{"mutated":true}']));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_template_versions')
            ->where('id', $versionId)
            ->delete());

        $this->assertSame(
            '{"currency":"EUR","lines":[]}',
            DB::table('finance_recurring_invoice_template_versions')
                ->where('id', $versionId)
                ->value('draft_snapshot'),
        );
    }

    public function test_current_version_and_runs_require_owner_matched_template_version_context(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $templateId = $this->insertTemplate((int) $owner->id, 50);
        $otherTemplateId = $this->insertTemplate((int) $otherOwner->id, 51);
        $sameOwnerOtherTemplateId = $this->insertTemplate((int) $owner->id, 52);
        $versionId = $this->insertTemplateVersion((int) $owner->id, $templateId, 1);
        $otherVersionId = $this->insertTemplateVersion((int) $otherOwner->id, $otherTemplateId, 1);
        $sameOwnerOtherVersionId = $this->insertTemplateVersion(
            (int) $owner->id,
            $sameOwnerOtherTemplateId,
            1,
        );

        DB::table('finance_recurring_invoice_templates')
            ->where('id', $templateId)
            ->update(['current_version_id' => $versionId]);

        $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_templates')
            ->where('id', $templateId)
            ->update(['current_version_id' => $otherVersionId]));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_templates')
            ->where('id', $sameOwnerOtherTemplateId)
            ->update(['current_version_id' => $versionId]));
        $this->expectConstraintViolation(fn (): int => $this->insertRun(
            (int) $owner->id,
            $templateId,
            $otherVersionId,
            53,
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertRun(
            (int) $owner->id,
            $templateId,
            $sameOwnerOtherVersionId,
            54,
        ));

        $runId = $this->insertRun((int) $owner->id, $templateId, $versionId, 55);
        $this->assertGreaterThan(0, $runId);
    }

    public function test_runs_are_unique_per_occurrence_and_idempotency_identity(): void
    {
        $owner = User::factory()->create();
        $templateId = $this->insertTemplate((int) $owner->id, 60);
        $versionId = $this->insertTemplateVersion((int) $owner->id, $templateId, 1);
        $runId = $this->insertRun((int) $owner->id, $templateId, $versionId, 61);

        $this->expectConstraintViolation(fn (): int => $this->insertRun(
            (int) $owner->id,
            $templateId,
            $versionId,
            62,
            ['scheduled_for' => '2026-08-31 06:00:00'],
        ));
        $idempotencyHash = (string) DB::table('finance_recurring_invoice_runs')
            ->where('id', $runId)
            ->value('idempotency_key_hash');
        $this->expectConstraintViolation(fn (): int => $this->insertRun(
            (int) $owner->id,
            $templateId,
            $versionId,
            63,
            [
                'scheduled_for' => '2026-09-30 06:00:00',
                'scheduled_local_date' => '2026-09-30',
                'idempotency_key_hash' => $idempotencyHash,
            ],
        ));

        $this->assertSame(1, DB::table('finance_recurring_invoice_runs')->count());
    }

    public function test_runs_require_exact_status_step_attempt_error_and_claim_values(): void
    {
        $owner = User::factory()->create();
        $templateId = $this->insertTemplate((int) $owner->id, 70);
        $versionId = $this->insertTemplateVersion((int) $owner->id, $templateId, 1);

        $suffix = 80;
        $day = 1;
        foreach ([
            'status' => ['status' => 'processing'],
            'step' => ['last_completed_step' => 'pdf_rendered'],
            'attempts' => ['attempts' => -1],
            'idempotency hash' => ['idempotency_key_hash' => str_repeat('g', 64)],
            'partial claim' => ['claim_token_hash' => hash('sha256', 'partial-claim')],
            'claim without expiry' => [
                'claim_token_hash' => hash('sha256', 'claim-without-expiry'),
                'claimed_at' => '2026-08-28 06:00:00',
            ],
            'expired claim' => [
                'claim_token_hash' => hash('sha256', 'expired-claim'),
                'claimed_at' => '2026-08-28 06:00:00',
                'claim_expires_at' => '2026-08-28 05:59:59',
            ],
            'blank error code' => ['last_error_code' => '', 'last_error_detail' => 'redacted'],
        ] as $index => $invalid) {
            $this->expectConstraintViolation(fn (): int => $this->insertRun(
                (int) $owner->id,
                $templateId,
                $versionId,
                $suffix++,
                array_merge([
                    'scheduled_for' => sprintf('2026-09-%02d 06:00:00', $day),
                    'scheduled_local_date' => sprintf('2026-09-%02d', $day++),
                ], $invalid),
            ), $index);
        }

        $claimedRunId = $this->insertRun((int) $owner->id, $templateId, $versionId, 90, [
            'claim_token_hash' => hash('sha256', 'valid-claim'),
            'claimed_at' => '2026-08-28 06:00:00',
            'claim_expires_at' => '2026-08-28 06:05:00',
        ]);
        $this->expectConstraintViolation(fn (): int => $this->insertRun(
            (int) $owner->id,
            $templateId,
            $versionId,
            91,
            [
                'scheduled_for' => '2026-09-30 06:00:00',
                'scheduled_local_date' => '2026-09-30',
                'claim_token_hash' => hash('sha256', 'valid-claim'),
                'claimed_at' => '2026-08-28 06:01:00',
                'claim_expires_at' => '2026-08-28 06:06:00',
            ],
        ));
        $this->assertGreaterThan(0, $claimedRunId);
    }

    public function test_run_invoice_and_delivery_references_are_owner_matched_and_write_once(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $templateId = $this->insertTemplate((int) $owner->id, 100);
        $versionId = $this->insertTemplateVersion((int) $owner->id, $templateId, 1);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 101);
        $otherInvoiceId = $this->invoiceFixture((int) $otherOwner->id, 102);
        $deliveryId = $this->deliveryFixture((int) $owner->id, $invoiceId, 103);
        $otherDeliveryId = $this->deliveryFixture((int) $otherOwner->id, $otherInvoiceId, 104);
        $runId = $this->insertRun((int) $owner->id, $templateId, $versionId, 105);

        $this->expectConstraintViolation(fn (): int => $this->insertRun(
            (int) $owner->id,
            $templateId,
            $versionId,
            106,
            [
                'scheduled_for' => '2026-09-30 06:00:00',
                'scheduled_local_date' => '2026-09-30',
                'invoice_id' => $otherInvoiceId,
            ],
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertRun(
            (int) $owner->id,
            $templateId,
            $versionId,
            107,
            [
                'scheduled_for' => '2026-10-31 07:00:00',
                'scheduled_local_date' => '2026-10-31',
                'invoice_id' => $invoiceId,
                'delivery_id' => $otherDeliveryId,
            ],
        ));

        DB::table('finance_recurring_invoice_runs')->where('id', $runId)->update([
            'status' => 'draft_created',
            'last_completed_step' => 'draft_created',
            'invoice_id' => $invoiceId,
            'attempts' => 1,
        ]);
        DB::table('finance_recurring_invoice_runs')->where('id', $runId)->update([
            'status' => 'sending',
            'last_completed_step' => 'delivery_staged',
            'delivery_id' => $deliveryId,
            'attempts' => 2,
        ]);

        $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_runs')
            ->where('id', $runId)
            ->update(['invoice_id' => null]));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_runs')
            ->where('id', $runId)
            ->update(['delivery_id' => null]));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_runs')
            ->where('id', $runId)
            ->update(['attempts' => 1]));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_runs')
            ->where('id', $runId)
            ->update(['last_completed_step' => 'draft_created']));

        $this->assertSame($invoiceId, (int) DB::table('finance_recurring_invoice_runs')->where('id', $runId)->value('invoice_id'));
        $this->assertSame($deliveryId, (int) DB::table('finance_recurring_invoice_runs')->where('id', $runId)->value('delivery_id'));
    }

    public function test_run_occurrence_context_and_execution_rows_cannot_be_retargeted_or_deleted(): void
    {
        $owner = User::factory()->create();
        $templateId = $this->insertTemplate((int) $owner->id, 110);
        $versionId = $this->insertTemplateVersion((int) $owner->id, $templateId, 1);
        $runId = $this->insertRun((int) $owner->id, $templateId, $versionId, 111);

        foreach ([
            ['uuid' => '018f4ca3-224d-7d8d-9f70-000000000112'],
            ['scheduled_for' => '2026-09-30 06:00:00'],
            ['scheduled_local_date' => '2026-09-30'],
            ['idempotency_key_hash' => hash('sha256', 'retarget-run')],
        ] as $change) {
            $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_runs')
                ->where('id', $runId)
                ->update($change));
        }

        $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_runs')
            ->where('id', $runId)
            ->delete());
    }

    public function test_parent_deletion_is_restricted_but_owner_deletion_cascades_all_history(): void
    {
        $owner = User::factory()->create();
        $templateId = $this->insertTemplate((int) $owner->id, 120);
        $versionId = $this->insertTemplateVersion((int) $owner->id, $templateId, 1);
        DB::table('finance_recurring_invoice_templates')->where('id', $templateId)->update([
            'current_version_id' => $versionId,
        ]);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 121);
        $deliveryId = $this->deliveryFixture((int) $owner->id, $invoiceId, 122);
        $this->insertRun((int) $owner->id, $templateId, $versionId, 123, [
            'invoice_id' => $invoiceId,
            'delivery_id' => $deliveryId,
        ]);

        $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_templates')
            ->where('id', $templateId)
            ->delete());
        $this->expectConstraintViolation(fn (): int => DB::table('finance_invoices')
            ->where('id', $invoiceId)
            ->delete());
        $this->expectConstraintViolation(fn (): int => DB::table('finance_invoice_deliveries')
            ->where('id', $deliveryId)
            ->delete());

        DB::transaction(fn (): int => DB::table('users')->where('id', $owner->id)->delete());

        foreach ([
            'finance_recurring_invoice_runs',
            'finance_recurring_invoice_template_versions',
            'finance_recurring_invoice_templates',
            'finance_invoice_deliveries',
            'finance_invoices',
        ] as $table) {
            $this->assertSame(0, DB::table($table)->where('user_id', $owner->id)->count());
        }
    }

    public function test_sqlite_replace_cannot_rewrite_template_version_or_run_identity(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('INSERT OR REPLACE is SQLite-specific.');
        }

        $owner = User::factory()->create();
        $templateId = $this->insertTemplate((int) $owner->id, 130);
        $versionId = $this->insertTemplateVersion((int) $owner->id, $templateId, 1);
        $runId = $this->insertRun((int) $owner->id, $templateId, $versionId, 131);

        $template = (array) DB::table('finance_recurring_invoice_templates')->find($templateId);
        $version = (array) DB::table('finance_recurring_invoice_template_versions')->find($versionId);
        $run = (array) DB::table('finance_recurring_invoice_runs')->find($runId);

        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_recurring_invoice_templates',
            array_merge($template, ['mode' => 'auto_send']),
        ));
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_recurring_invoice_template_versions',
            array_merge($version, ['draft_snapshot' => '{"mutated":true}']),
        ));
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_recurring_invoice_runs',
            array_merge($run, ['status' => 'sent']),
        ));
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_recurring_invoice_templates',
            array_merge($template, ['id' => $templateId + 10_000]),
        ));
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_recurring_invoice_template_versions',
            array_merge($version, [
                'id' => $versionId + 10_000,
                'effective_from' => '2026-09-01',
            ]),
        ));
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_recurring_invoice_template_versions',
            array_merge($version, [
                'id' => $versionId + 20_000,
                'version_number' => 2,
            ]),
        ));
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_recurring_invoice_runs',
            array_merge($run, [
                'id' => $runId + 10_000,
                'scheduled_for' => '2026-09-30 06:00:00',
                'scheduled_local_date' => '2026-09-30',
                'idempotency_key_hash' => hash('sha256', 'replace-run-uuid'),
            ]),
        ));
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_recurring_invoice_runs',
            array_merge($run, [
                'id' => $runId + 20_000,
                'uuid' => '018f4ca3-224d-7d8d-9f70-000000000132',
                'idempotency_key_hash' => hash('sha256', 'replace-run-occurrence'),
            ]),
        ));
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_recurring_invoice_runs',
            array_merge($run, [
                'id' => $runId + 30_000,
                'uuid' => '018f4ca3-224d-7d8d-9f70-000000000133',
                'scheduled_for' => '2026-10-31 07:00:00',
                'scheduled_local_date' => '2026-10-31',
            ]),
        ));

        $this->assertSame('draft', DB::table('finance_recurring_invoice_templates')->where('id', $templateId)->value('mode'));
        $this->assertSame('{"currency":"EUR","lines":[]}', DB::table('finance_recurring_invoice_template_versions')->where('id', $versionId)->value('draft_snapshot'));
        $this->assertSame('pending', DB::table('finance_recurring_invoice_runs')->where('id', $runId)->value('status'));
    }

    public function test_recurring_primary_keys_reject_zero_and_negative_inserts_including_sqlite_replace(): void
    {
        $owner = User::factory()->create();
        $templateId = $this->insertTemplate((int) $owner->id, 135);
        $versionId = $this->insertTemplateVersion((int) $owner->id, $templateId, 1);
        $runId = $this->insertRun((int) $owner->id, $templateId, $versionId, 136);

        foreach ([0, -1] as $invalidId) {
            $template = array_merge(
                (array) DB::table('finance_recurring_invoice_templates')->find($templateId),
                [
                    'id' => $invalidId,
                    'uuid' => sprintf(
                        '018f4ca3-224d-7d8d-9f60-%012d',
                        $invalidId === 0 ? 137 : 138,
                    ),
                ],
            );
            $version = array_merge(
                (array) DB::table('finance_recurring_invoice_template_versions')->find($versionId),
                [
                    'id' => $invalidId,
                    'version_number' => $invalidId === 0 ? 2 : 3,
                    'effective_from' => $invalidId === 0 ? '2026-09-01' : '2026-10-01',
                ],
            );
            $run = array_merge(
                (array) DB::table('finance_recurring_invoice_runs')->find($runId),
                [
                    'id' => $invalidId,
                    'uuid' => sprintf(
                        '018f4ca3-224d-7d8d-9f70-%012d',
                        $invalidId === 0 ? 139 : 140,
                    ),
                    'scheduled_for' => $invalidId === 0
                        ? '2026-09-30 06:00:00'
                        : '2026-10-31 07:00:00',
                    'scheduled_local_date' => $invalidId === 0 ? '2026-09-30' : '2026-10-31',
                    'idempotency_key_hash' => hash('sha256', "invalid-run-{$invalidId}"),
                ],
            );

            foreach ([
                'finance_recurring_invoice_templates' => $template,
                'finance_recurring_invoice_template_versions' => $version,
                'finance_recurring_invoice_runs' => $run,
            ] as $table => $row) {
                $this->expectConstraintViolation(fn (): bool => DB::table($table)->insert($row));

                if (DB::getDriverName() === 'sqlite') {
                    $this->expectConstraintViolation(fn () => $this->replaceRow($table, $row));
                }

                $this->assertFalse(DB::table($table)->where('id', $invalidId)->exists());
            }
        }
    }

    public function test_postgresql_executes_core_contract_and_reapply_when_configured(): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');

        if (! extension_loaded('pdo_pgsql') || ! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped(
                'Set FINANCE_TEST_PGSQL_URL and install pdo_pgsql to run the PostgreSQL execution contract.',
            );
        }

        $defaultConnection = DB::getDefaultConnection();
        $postgresConnection = 'pgsql_recurring_execution';
        $schema = 'finance_recurring_task4_'.bin2hex(random_bytes(8));
        config([
            "database.connections.{$postgresConnection}" => array_merge(
                config('database.connections.pgsql'),
                ['url' => $postgresUrl, 'search_path' => 'public'],
            ),
        ]);
        DB::purge($postgresConnection);
        $connection = DB::connection($postgresConnection);
        $schemaCreated = false;

        try {
            $connection->statement("CREATE SCHEMA \"{$schema}\"");
            $schemaCreated = true;
            $connection->statement("SET search_path TO \"{$schema}\"");
            DB::setDefaultConnection($postgresConnection);
            Schema::clearResolvedInstance('db.schema');

            Schema::create('users', function (Blueprint $table): void {
                $table->id();
            });
            $foundationMigration = require database_path('migrations/2026_08_28_100000_create_finance_document_core.php');
            $invoiceMigration = require database_path('migrations/2026_08_28_110000_create_finance_invoices.php');
            $paymentMigration = require database_path('migrations/2026_08_28_110100_create_finance_payments.php');
            $recurringMigration = require database_path('migrations/2026_08_28_110200_create_finance_recurring_invoices.php');
            $foundationMigration->up();
            $invoiceMigration->up();
            $paymentMigration->up();
            $recurringMigration->up();
            DB::table('users')->insert([['id' => 1], ['id' => 2]]);

            $templateId = $this->insertTemplate(1, 140);
            $versionId = $this->insertTemplateVersion(1, $templateId, 1);
            $runId = $this->insertRun(1, $templateId, $versionId, 141);
            $foreignTemplateId = $this->insertTemplate(2, 142);
            $foreignVersionId = $this->insertTemplateVersion(2, $foreignTemplateId, 1);

            $this->expectConstraintViolation(fn (): int => $this->insertRun(
                1,
                $templateId,
                $foreignVersionId,
                143,
                [
                    'scheduled_for' => '2026-09-30 06:00:00',
                    'scheduled_local_date' => '2026-09-30',
                ],
            ));
            $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_template_versions')
                ->where('id', $versionId)
                ->update(['draft_snapshot' => '{"mutated":true}']));
            $this->expectConstraintViolation(fn (): int => DB::table('finance_recurring_invoice_runs')
                ->where('id', $runId)
                ->delete());

            DB::transaction(fn (): int => DB::table('users')->where('id', 1)->delete());
            $this->assertSame(0, DB::table('finance_recurring_invoice_templates')->where('user_id', 1)->count());
            $this->assertSame(0, DB::table('finance_recurring_invoice_template_versions')->where('user_id', 1)->count());
            $this->assertSame(0, DB::table('finance_recurring_invoice_runs')->where('user_id', 1)->count());

            $recurringMigration->down();
            $this->assertFalse(Schema::hasTable('finance_recurring_invoice_templates'));
            $recurringMigration->up();
            $this->assertTrue(Schema::hasTable('finance_recurring_invoice_templates'));
            $this->assertGreaterThan(0, $this->insertTemplate(2, 144));
        } finally {
            DB::setDefaultConnection($defaultConnection);
            Schema::clearResolvedInstance('db.schema');

            try {
                if ($schemaCreated) {
                    $connection->statement('SET search_path TO public');
                    $connection->statement("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE");
                }
            } finally {
                DB::purge($postgresConnection);
            }
        }
    }

    public function test_postgresql_ddl_uses_deferred_owner_relations_checks_and_history_guards(): void
    {
        $defaultConnection = DB::getDefaultConnection();
        $postgresConnection = 'pgsql_recurring_ddl';
        config([
            "database.connections.{$postgresConnection}" => array_merge(
                config('database.connections.pgsql'),
                ['database' => 'ledgerline_recurring_ddl_inspection'],
            ),
        ]);
        DB::setDefaultConnection($postgresConnection);
        Schema::clearResolvedInstance('db.schema');

        try {
            $migration = require database_path('migrations/2026_08_28_110200_create_finance_recurring_invoices.php');
            $upQueries = DB::connection()->pretend(function () use ($migration): void {
                $migration->up();
            });
            $downQueries = DB::connection()->pretend(function () use ($migration): void {
                $migration->down();
            });
        } finally {
            DB::setDefaultConnection($defaultConnection);
            DB::purge($postgresConnection);
            Schema::clearResolvedInstance('db.schema');
        }

        $ddl = strtolower(implode("\n", array_column($upQueries, 'query')));
        $downDdl = strtolower(implode("\n", array_column($downQueries, 'query')));

        foreach ([
            'finance_recurring_versions_owner_template_foreign',
            'finance_recurring_templates_current_version_foreign',
            'finance_recurring_runs_owner_version_foreign',
            'finance_recurring_runs_owner_invoice_foreign',
            'finance_recurring_runs_owner_delivery_invoice_foreign',
        ] as $constraint) {
            $this->assertMatchesRegularExpression(
                "/{$constraint}.*on delete no action deferrable initially deferred/",
                $ddl,
            );
        }

        foreach ([
            'finance_recurring_templates_integrity_check',
            'finance_recurring_versions_integrity_check',
            'finance_recurring_runs_integrity_check',
            'finance_recurring_history_immutable_guard',
            'finance_recurring_run_progress_guard',
        ] as $name) {
            $this->assertStringContainsString($name, $ddl);
        }

        $this->assertStringContainsString('drop constraint if exists finance_recurring_templates_current_version_foreign', $downDdl);
        $this->assertStringContainsString('drop index if exists finance_invoice_deliveries_owner_id_invoice_unique', $downDdl);
        $this->assertStringContainsString('drop function if exists finance_recurring_history_immutable_guard()', $downDdl);
        $this->assertStringContainsString('drop function if exists finance_recurring_run_progress_guard()', $downDdl);
    }

    public function test_migration_can_be_rolled_back_and_applied_again(): void
    {
        $migration = require database_path('migrations/2026_08_28_110200_create_finance_recurring_invoices.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('finance_recurring_invoice_runs'));
        $this->assertFalse(Schema::hasTable('finance_recurring_invoice_template_versions'));
        $this->assertFalse(Schema::hasTable('finance_recurring_invoice_templates'));

        $migration->up();

        $this->assertTrue(Schema::hasTable('finance_recurring_invoice_templates'));
        $this->assertTrue(Schema::hasTable('finance_recurring_invoice_template_versions'));
        $this->assertTrue(Schema::hasTable('finance_recurring_invoice_runs'));
    }

    /** @param array<string, mixed> $overrides */
    private function insertTemplate(int $userId, int $suffix, array $overrides = []): int
    {
        return (int) DB::table('finance_recurring_invoice_templates')->insertGetId(array_merge([
            'user_id' => $userId,
            'uuid' => sprintf('018f4ca3-224d-7d8d-9f60-%012d', $suffix),
            'mode' => 'draft',
            'interval' => 'monthly',
            'timezone' => 'Europe/Berlin',
            'start_date' => '2026-08-28',
            'end_date' => null,
            'run_time' => '08:00:00',
            'anchor_day' => 28,
            'month_end_anchor' => false,
            'next_run_at' => '2026-08-31 06:00:00',
            'status' => 'active',
            'paused_at' => null,
            'current_version_id' => null,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertTemplateVersion(
        int $userId,
        int $templateId,
        int $versionNumber,
        array $overrides = [],
    ): int {
        return (int) DB::table('finance_recurring_invoice_template_versions')->insertGetId(array_merge([
            'user_id' => $userId,
            'template_id' => $templateId,
            'version_number' => $versionNumber,
            'effective_from' => '2026-08-28',
            'draft_snapshot' => '{"currency":"EUR","lines":[]}',
            'snapshot_sha256' => hash('sha256', '{"currency":"EUR","lines":[]}'),
            'created_by' => $userId,
            'created_at' => now(),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertRun(
        int $userId,
        int $templateId,
        int $templateVersionId,
        int $suffix,
        array $overrides = [],
    ): int {
        return (int) DB::table('finance_recurring_invoice_runs')->insertGetId(array_merge([
            'user_id' => $userId,
            'uuid' => sprintf('018f4ca3-224d-7d8d-9f70-%012d', $suffix),
            'template_id' => $templateId,
            'template_version_id' => $templateVersionId,
            'scheduled_for' => '2026-08-31 06:00:00',
            'scheduled_local_date' => '2026-08-31',
            'status' => 'pending',
            'last_completed_step' => null,
            'invoice_id' => null,
            'delivery_id' => null,
            'attempts' => 0,
            'idempotency_key_hash' => hash('sha256', "run-{$userId}-{$templateId}-{$suffix}"),
            'claim_token_hash' => null,
            'claimed_at' => null,
            'claim_expires_at' => null,
            'next_retry_at' => null,
            'last_error_code' => null,
            'last_error_detail' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function invoiceFixture(int $userId, int $suffix): int
    {
        $now = now();
        $seriesId = (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => $userId,
            'uuid' => sprintf('018f4ca3-224d-7d8d-9f10-%012d', $suffix),
            'document_type' => 'invoice',
            'status' => 'finalized',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $userId,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'previous_revision_id' => null,
            'status' => 'published',
            'snapshot' => '{}',
            'net_minor' => 11_900,
            'vat_minor' => 0,
            'gross_minor' => 11_900,
            'currency' => 'EUR',
            'published_at' => $now,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        return (int) DB::table('finance_invoices')->insertGetId([
            'user_id' => $userId,
            'uuid' => sprintf('018f4ca3-224d-7d8d-9f20-%012d', $suffix),
            'document_series_id' => $seriesId,
            'current_revision_id' => $revisionId,
            'kind' => 'invoice',
            'number' => sprintf('RE-2026-%04d', $suffix),
            'year' => 2026,
            'sequence' => $suffix,
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'workflow_status' => 'finalized',
            'finalized_at' => $now,
            'allocated_minor' => 0,
            'open_minor' => 11_900,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function deliveryFixture(int $userId, int $invoiceId, int $suffix): int
    {
        $invoice = DB::table('finance_invoices')->find($invoiceId);

        return (int) DB::table('finance_invoice_deliveries')->insertGetId([
            'user_id' => $userId,
            'uuid' => sprintf('018f4ca3-224d-7d8d-9f30-%012d', $suffix),
            'invoice_id' => $invoiceId,
            'document_series_id' => $invoice->document_series_id,
            'document_revision_id' => $invoice->current_revision_id,
            'kind' => 'invoice',
            'recipient' => "customer-{$suffix}@example.test",
            'message_id' => "<delivery-{$suffix}@ledgerline.test>",
            'status' => 'sent',
            'attempts' => 1,
            'idempotency_key_hash' => hash('sha256', "delivery-{$suffix}"),
            'request_hash' => hash('sha256', "delivery-request-{$suffix}"),
            'queued_at' => now(),
            'last_attempt_at' => now(),
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function replaceRow(string $table, array $row): void
    {
        $columns = array_keys($row);
        $quotedColumns = implode(', ', array_map(
            static fn (string $column): string => '"'.$column.'"',
            $columns,
        ));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        DB::statement(
            "INSERT OR REPLACE INTO \"{$table}\" ({$quotedColumns}) VALUES ({$placeholders})",
            array_values($row),
        );
    }

    /** @param callable(): mixed $operation */
    private function expectConstraintViolation(callable $operation, string $label = ''): void
    {
        try {
            $operation();
            $this->fail("Expected a database constraint violation: {$label}");
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
