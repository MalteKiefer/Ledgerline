<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_recurring_invoice_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('mode', 32);
            $table->string('interval', 32);
            $table->string('timezone', 64);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('run_time');
            $table->unsignedTinyInteger('anchor_day');
            $table->boolean('month_end_anchor')->default(false);
            $table->timestamp('next_run_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('paused_at')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique(
                ['user_id', 'id'],
                'finance_recurring_templates_owner_id_unique',
            );
            $table->unique(
                ['user_id', 'id', 'current_version_id'],
                'finance_recurring_templates_owner_current_unique',
            );
            $table->unique(
                ['user_id', 'uuid'],
                'finance_recurring_templates_owner_uuid_unique',
            );
            $table->index(
                ['user_id', 'status', 'next_run_at'],
                'finance_recurring_templates_owner_status_next_index',
            );
        });

        Schema::create('finance_recurring_invoice_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id');
            $table->unsignedInteger('version_number');
            $table->date('effective_from');
            $table->jsonb('draft_snapshot');
            $table->char('snapshot_sha256', 64);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['user_id', 'template_id', 'id'],
                'finance_recurring_versions_owner_context_unique',
            );
            $table->unique(
                ['user_id', 'template_id', 'version_number'],
                'finance_recurring_versions_owner_number_unique',
            );
            $table->unique(
                ['user_id', 'template_id', 'effective_from'],
                'finance_recurring_versions_owner_effective_unique',
            );
            $table->foreign(
                ['user_id', 'template_id'],
                'finance_recurring_versions_owner_template_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_recurring_invoice_templates')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                'created_by',
                'finance_recurring_versions_creator_foreign',
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
        $this->addVersionChecks();
        $this->addCurrentVersionRelation();
        $this->addTemplateChecks();

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX finance_invoice_deliveries_owner_id_invoice_unique
            ON finance_invoice_deliveries (user_id, id, invoice_id)
            SQL);

        Schema::create('finance_recurring_invoice_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('template_id');
            $table->foreignId('template_version_id');
            $table->timestamp('scheduled_for');
            $table->date('scheduled_local_date');
            $table->string('status', 32)->default('pending');
            $table->string('last_completed_step', 32)->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->char('idempotency_key_hash', 64);
            $table->char('claim_token_hash', 64)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->string('last_error_code', 128)->nullable();
            $table->string('last_error_detail', 512)->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'id'],
                'finance_recurring_runs_owner_id_unique',
            );
            $table->unique(
                ['user_id', 'uuid'],
                'finance_recurring_runs_owner_uuid_unique',
            );
            $table->unique(
                ['template_id', 'scheduled_for'],
                'finance_recurring_runs_occurrence_unique',
            );
            $table->unique(
                ['user_id', 'idempotency_key_hash'],
                'finance_recurring_runs_owner_idempotency_unique',
            );
            $table->foreign(
                ['user_id', 'template_id', 'template_version_id'],
                'finance_recurring_runs_owner_version_foreign',
            )
                ->references(['user_id', 'template_id', 'id'])
                ->on('finance_recurring_invoice_template_versions')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'invoice_id'],
                'finance_recurring_runs_owner_invoice_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_invoices')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'delivery_id', 'invoice_id'],
                'finance_recurring_runs_owner_delivery_invoice_foreign',
            )
                ->references(['user_id', 'id', 'invoice_id'])
                ->on('finance_invoice_deliveries')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['user_id', 'status', 'next_retry_at'],
                'finance_recurring_runs_owner_status_retry_index',
            );
            $table->index(
                ['template_id', 'scheduled_for'],
                'finance_recurring_runs_template_schedule_index',
            );
        });
        $this->addRunClaimIndex();
        $this->addRunChecks();
        $this->addPrimaryKeyChecks();
        $this->addHistoryGuards();
        $this->addRunProgressGuards();
        $this->addSqliteReplaceGuards();
    }

    public function down(): void
    {
        $driver = $this->assertSupportedDriver();

        Schema::dropIfExists('finance_recurring_invoice_runs');

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_recurring_invoice_templates
                DROP CONSTRAINT IF EXISTS finance_recurring_templates_current_version_foreign
                SQL);
        }

        Schema::dropIfExists('finance_recurring_invoice_template_versions');
        Schema::dropIfExists('finance_recurring_invoice_templates');
        DB::statement('DROP INDEX IF EXISTS finance_invoice_deliveries_owner_id_invoice_unique');

        if ($driver === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS finance_recurring_history_immutable_guard()');
            DB::unprepared('DROP FUNCTION IF EXISTS finance_recurring_run_progress_guard()');
        }
    }

    private function addTemplateChecks(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_recurring_invoice_templates
                ADD CONSTRAINT finance_recurring_templates_integrity_check
                    CHECK (
                        id > 0
                        AND mode IN ('draft', 'auto_send')
                        AND interval IN ('monthly', 'quarterly', 'semiannual', 'annual')
                        AND length(timezone) BETWEEN 1 AND 64
                        AND btrim(timezone) = timezone
                        AND anchor_day BETWEEN 1 AND 31
                        AND (end_date IS NULL OR end_date >= start_date)
                    ),
                ADD CONSTRAINT finance_recurring_templates_state_check
                    CHECK (
                        status IN ('active', 'paused', 'completed')
                        AND (
                            (status = 'paused' AND paused_at IS NOT NULL AND next_run_at IS NOT NULL)
                            OR (status = 'active' AND paused_at IS NULL AND next_run_at IS NOT NULL)
                            OR (status = 'completed' AND paused_at IS NULL AND next_run_at IS NULL)
                        )
                    ),
                ADD CONSTRAINT finance_recurring_templates_version_check
                    CHECK (version >= 0)
                SQL);

            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_recurring_templates_integrity_{$operation}_check
                BEFORE {$operation} ON finance_recurring_invoice_templates
                WHEN
                    NEW.mode NOT IN ('draft', 'auto_send')
                    OR NEW.interval NOT IN ('monthly', 'quarterly', 'semiannual', 'annual')
                    OR length(NEW.timezone) NOT BETWEEN 1 AND 64
                    OR trim(NEW.timezone) <> NEW.timezone
                    OR strftime('%H:%M:%S', NEW.run_time) IS NULL
                    OR strftime('%H:%M:%S', NEW.run_time) <> NEW.run_time
                    OR NEW.anchor_day NOT BETWEEN 1 AND 31
                    OR NEW.month_end_anchor NOT IN (0, 1)
                    OR (NEW.end_date IS NOT NULL AND NEW.end_date < NEW.start_date)
                    OR NEW.status NOT IN ('active', 'paused', 'completed')
                    OR NOT (
                        (NEW.status = 'paused' AND NEW.paused_at IS NOT NULL AND NEW.next_run_at IS NOT NULL)
                        OR (NEW.status = 'active' AND NEW.paused_at IS NULL AND NEW.next_run_at IS NOT NULL)
                        OR (NEW.status = 'completed' AND NEW.paused_at IS NULL AND NEW.next_run_at IS NULL)
                    )
                    OR NEW.version < 0
                BEGIN
                    SELECT RAISE(ABORT, 'finance_recurring_templates_integrity_check');
                END
                SQL);
        }
    }

    private function addCurrentVersionRelation(): void
    {
        if ($this->assertSupportedDriver() === 'pgsql') {
            Schema::table('finance_recurring_invoice_templates', function (Blueprint $table): void {
                $table->foreign(
                    ['user_id', 'id', 'current_version_id'],
                    'finance_recurring_templates_current_version_foreign',
                )
                    ->references(['user_id', 'template_id', 'id'])
                    ->on('finance_recurring_invoice_template_versions')
                    ->noActionOnDelete()
                    ->deferrable()
                    ->initiallyImmediate(false);
            });

            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_recurring_templates_current_version_{$operation}_check
                BEFORE {$operation} ON finance_recurring_invoice_templates
                WHEN
                    NEW.current_version_id IS NOT NULL
                    AND NOT EXISTS (
                        SELECT 1
                        FROM finance_recurring_invoice_template_versions
                        WHERE user_id = NEW.user_id
                            AND template_id = NEW.id
                            AND id = NEW.current_version_id
                    )
                BEGIN
                    SELECT RAISE(ABORT, 'finance_recurring_templates_current_version_foreign');
                END
                SQL);
        }
    }

    private function addVersionChecks(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_recurring_invoice_template_versions
                ADD CONSTRAINT finance_recurring_versions_integrity_check
                    CHECK (
                        id > 0
                        AND version_number > 0
                        AND jsonb_typeof(draft_snapshot) = 'object'
                        AND snapshot_sha256 ~ '^[0-9a-f]{64}$'
                    )
                SQL);

            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_recurring_versions_integrity_insert_check
            BEFORE INSERT ON finance_recurring_invoice_template_versions
            WHEN
                NEW.version_number <= 0
                OR json_valid(NEW.draft_snapshot) <> 1
                OR json_type(NEW.draft_snapshot) <> 'object'
                OR length(NEW.snapshot_sha256) <> 64
                OR NEW.snapshot_sha256 GLOB '*[^0-9a-f]*'
            BEGIN
                SELECT RAISE(ABORT, 'finance_recurring_versions_integrity_check');
            END
            SQL);
    }

    private function addRunClaimIndex(): void
    {
        $this->assertSupportedDriver();

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX finance_recurring_runs_owner_claim_unique
            ON finance_recurring_invoice_runs (user_id, claim_token_hash)
            WHERE claim_token_hash IS NOT NULL
            SQL);
    }

    private function addRunChecks(): void
    {
        $driver = $this->assertSupportedDriver();
        $states = "'pending', 'creating_draft', 'draft_created', 'finalizing', 'finalized', 'sending', 'sent', 'failed'";
        $steps = "'draft_created', 'finalized', 'delivery_staged', 'sent'";

        if ($driver === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE finance_recurring_invoice_runs
                ADD CONSTRAINT finance_recurring_runs_integrity_check
                    CHECK (
                        id > 0
                        AND status IN ({$states})
                        AND (last_completed_step IS NULL OR last_completed_step IN ({$steps}))
                        AND attempts >= 0
                        AND idempotency_key_hash ~ '^[0-9a-f]{64}$'
                        AND (
                            (
                                claim_token_hash IS NULL
                                AND claimed_at IS NULL
                                AND claim_expires_at IS NULL
                            )
                            OR (
                                claim_token_hash ~ '^[0-9a-f]{64}$'
                                AND claimed_at IS NOT NULL
                                AND claim_expires_at IS NOT NULL
                                AND claim_expires_at > claimed_at
                            )
                        )
                        AND (delivery_id IS NULL OR invoice_id IS NOT NULL)
                        AND (
                            last_error_code IS NULL
                            OR length(btrim(last_error_code)) BETWEEN 1 AND 128
                        )
                        AND (last_error_detail IS NULL OR last_error_code IS NOT NULL)
                    )
                SQL);

            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_recurring_runs_integrity_{$operation}_check
                BEFORE {$operation} ON finance_recurring_invoice_runs
                WHEN
                    NEW.status NOT IN ({$states})
                    OR (
                        NEW.last_completed_step IS NOT NULL
                        AND NEW.last_completed_step NOT IN ({$steps})
                    )
                    OR NEW.attempts < 0
                    OR length(NEW.idempotency_key_hash) <> 64
                    OR NEW.idempotency_key_hash GLOB '*[^0-9a-f]*'
                    OR NOT (
                        (
                            NEW.claim_token_hash IS NULL
                            AND NEW.claimed_at IS NULL
                            AND NEW.claim_expires_at IS NULL
                        )
                        OR (
                            NEW.claim_token_hash IS NOT NULL
                            AND length(NEW.claim_token_hash) = 64
                            AND NEW.claim_token_hash NOT GLOB '*[^0-9a-f]*'
                            AND NEW.claimed_at IS NOT NULL
                            AND NEW.claim_expires_at IS NOT NULL
                            AND NEW.claim_expires_at > NEW.claimed_at
                        )
                    )
                    OR (NEW.delivery_id IS NOT NULL AND NEW.invoice_id IS NULL)
                    OR (
                        NEW.last_error_code IS NOT NULL
                        AND length(trim(NEW.last_error_code)) NOT BETWEEN 1 AND 128
                    )
                    OR (NEW.last_error_detail IS NOT NULL AND NEW.last_error_code IS NULL)
                BEGIN
                    SELECT RAISE(ABORT, 'finance_recurring_runs_integrity_check');
                END
                SQL);
        }
    }

    private function addPrimaryKeyChecks(): void
    {
        if ($this->assertSupportedDriver() !== 'sqlite') {
            return;
        }

        $tables = [
            'finance_recurring_invoice_templates' => 'finance_recurring_templates_id_positive_check',
            'finance_recurring_invoice_template_versions' => 'finance_recurring_versions_id_positive_check',
            'finance_recurring_invoice_runs' => 'finance_recurring_runs_id_positive_check',
        ];

        foreach ($tables as $table => $constraint) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$constraint}_insert
                AFTER INSERT ON {$table}
                WHEN NEW.id <= 0
                BEGIN
                    SELECT RAISE(ABORT, '{$constraint}');
                END
                SQL);
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$constraint}_update
                BEFORE UPDATE OF id ON {$table}
                WHEN NEW.id <= 0
                BEGIN
                    SELECT RAISE(ABORT, '{$constraint}');
                END
                SQL);
        }
    }

    private function addHistoryGuards(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION finance_recurring_history_immutable_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM users WHERE id = OLD.user_id) THEN
                        RAISE EXCEPTION 'finance_recurring_history_immutable_guard'
                            USING ERRCODE = '23514';
                    END IF;

                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;

                    RETURN NEW;
                END;
                $$
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER finance_recurring_versions_immutable
                BEFORE UPDATE OR DELETE ON finance_recurring_invoice_template_versions
                FOR EACH ROW
                EXECUTE FUNCTION finance_recurring_history_immutable_guard()
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER finance_recurring_runs_delete_immutable
                BEFORE DELETE ON finance_recurring_invoice_runs
                FOR EACH ROW
                EXECUTE FUNCTION finance_recurring_history_immutable_guard()
                SQL);

            return;
        }

        foreach (['update', 'delete'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_recurring_versions_immutable_{$operation}
                BEFORE {$operation} ON finance_recurring_invoice_template_versions
                WHEN EXISTS (SELECT 1 FROM users WHERE id = OLD.user_id)
                BEGIN
                    SELECT RAISE(ABORT, 'finance_recurring_history_immutable_guard');
                END
                SQL);
        }
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_recurring_runs_delete_immutable
            BEFORE DELETE ON finance_recurring_invoice_runs
            WHEN EXISTS (SELECT 1 FROM users WHERE id = OLD.user_id)
            BEGIN
                SELECT RAISE(ABORT, 'finance_recurring_history_immutable_guard');
            END
            SQL);
    }

    private function addRunProgressGuards(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION finance_recurring_run_progress_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF NEW.id IS DISTINCT FROM OLD.id
                        OR NEW.user_id IS DISTINCT FROM OLD.user_id
                        OR NEW.uuid IS DISTINCT FROM OLD.uuid
                        OR NEW.template_id IS DISTINCT FROM OLD.template_id
                        OR NEW.template_version_id IS DISTINCT FROM OLD.template_version_id
                        OR NEW.scheduled_for IS DISTINCT FROM OLD.scheduled_for
                        OR NEW.scheduled_local_date IS DISTINCT FROM OLD.scheduled_local_date
                        OR NEW.idempotency_key_hash IS DISTINCT FROM OLD.idempotency_key_hash
                        OR NEW.created_at IS DISTINCT FROM OLD.created_at
                        OR (OLD.invoice_id IS NOT NULL AND NEW.invoice_id IS DISTINCT FROM OLD.invoice_id)
                        OR (OLD.delivery_id IS NOT NULL AND NEW.delivery_id IS DISTINCT FROM OLD.delivery_id)
                        OR NEW.attempts < OLD.attempts
                        OR COALESCE(array_position(
                            ARRAY['draft_created', 'finalized', 'delivery_staged', 'sent']::text[],
                            NEW.last_completed_step
                        ), 0) < COALESCE(array_position(
                            ARRAY['draft_created', 'finalized', 'delivery_staged', 'sent']::text[],
                            OLD.last_completed_step
                        ), 0)
                    THEN
                        RAISE EXCEPTION 'finance_recurring_run_progress_guard'
                            USING ERRCODE = '23514';
                    END IF;

                    RETURN NEW;
                END;
                $$
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER finance_recurring_runs_progress_guard
                BEFORE UPDATE ON finance_recurring_invoice_runs
                FOR EACH ROW
                EXECUTE FUNCTION finance_recurring_run_progress_guard()
                SQL);

            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_recurring_runs_progress_guard
            BEFORE UPDATE ON finance_recurring_invoice_runs
            WHEN
                NEW.id IS NOT OLD.id
                OR NEW.user_id IS NOT OLD.user_id
                OR NEW.uuid IS NOT OLD.uuid
                OR NEW.template_id IS NOT OLD.template_id
                OR NEW.template_version_id IS NOT OLD.template_version_id
                OR NEW.scheduled_for IS NOT OLD.scheduled_for
                OR NEW.scheduled_local_date IS NOT OLD.scheduled_local_date
                OR NEW.idempotency_key_hash IS NOT OLD.idempotency_key_hash
                OR NEW.created_at IS NOT OLD.created_at
                OR (OLD.invoice_id IS NOT NULL AND NEW.invoice_id IS NOT OLD.invoice_id)
                OR (OLD.delivery_id IS NOT NULL AND NEW.delivery_id IS NOT OLD.delivery_id)
                OR NEW.attempts < OLD.attempts
                OR (
                    CASE NEW.last_completed_step
                        WHEN 'draft_created' THEN 1
                        WHEN 'finalized' THEN 2
                        WHEN 'delivery_staged' THEN 3
                        WHEN 'sent' THEN 4
                        ELSE 0
                    END
                    <
                    CASE OLD.last_completed_step
                        WHEN 'draft_created' THEN 1
                        WHEN 'finalized' THEN 2
                        WHEN 'delivery_staged' THEN 3
                        WHEN 'sent' THEN 4
                        ELSE 0
                    END
                )
            BEGIN
                SELECT RAISE(ABORT, 'finance_recurring_run_progress_guard');
            END
            SQL);
    }

    private function addSqliteReplaceGuards(): void
    {
        if ($this->assertSupportedDriver() !== 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_recurring_templates_insert_conflict_guard
            BEFORE INSERT ON finance_recurring_invoice_templates
            WHEN
                EXISTS (
                    SELECT 1 FROM finance_recurring_invoice_templates WHERE id = NEW.id
                )
                OR EXISTS (
                    SELECT 1 FROM finance_recurring_invoice_templates
                    WHERE user_id = NEW.user_id AND uuid = NEW.uuid
                )
            BEGIN
                SELECT RAISE(ABORT, 'finance_recurring_templates_insert_conflict_guard');
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_recurring_versions_insert_conflict_guard
            BEFORE INSERT ON finance_recurring_invoice_template_versions
            WHEN
                EXISTS (
                    SELECT 1 FROM finance_recurring_invoice_template_versions WHERE id = NEW.id
                )
                OR EXISTS (
                    SELECT 1 FROM finance_recurring_invoice_template_versions
                    WHERE user_id = NEW.user_id
                        AND template_id = NEW.template_id
                        AND version_number = NEW.version_number
                )
                OR EXISTS (
                    SELECT 1 FROM finance_recurring_invoice_template_versions
                    WHERE user_id = NEW.user_id
                        AND template_id = NEW.template_id
                        AND effective_from = NEW.effective_from
                )
            BEGIN
                SELECT RAISE(ABORT, 'finance_recurring_versions_insert_conflict_guard');
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_recurring_runs_insert_conflict_guard
            BEFORE INSERT ON finance_recurring_invoice_runs
            WHEN
                EXISTS (
                    SELECT 1 FROM finance_recurring_invoice_runs WHERE id = NEW.id
                )
                OR EXISTS (
                    SELECT 1 FROM finance_recurring_invoice_runs
                    WHERE user_id = NEW.user_id AND uuid = NEW.uuid
                )
                OR EXISTS (
                    SELECT 1 FROM finance_recurring_invoice_runs
                    WHERE template_id = NEW.template_id
                        AND scheduled_for = NEW.scheduled_for
                )
                OR EXISTS (
                    SELECT 1 FROM finance_recurring_invoice_runs
                    WHERE user_id = NEW.user_id
                        AND idempotency_key_hash = NEW.idempotency_key_hash
                )
                OR (
                    NEW.claim_token_hash IS NOT NULL
                    AND EXISTS (
                        SELECT 1 FROM finance_recurring_invoice_runs
                        WHERE user_id = NEW.user_id
                            AND claim_token_hash = NEW.claim_token_hash
                    )
                )
            BEGIN
                SELECT RAISE(ABORT, 'finance_recurring_runs_insert_conflict_guard');
            END
            SQL);
    }

    private function assertSupportedDriver(): string
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new LogicException("Unsupported database driver: {$driver}");
        }

        return $driver;
    }
};
