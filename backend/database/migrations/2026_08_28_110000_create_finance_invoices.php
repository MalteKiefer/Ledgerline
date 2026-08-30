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
        $this->addRevisionCurrencyCheck();

        Schema::create('finance_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('document_series_id');
            $table->foreignId('current_revision_id');
            $table->string('kind', 32);
            $table->string('number', 64)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('source_type', 64)->nullable();
            $table->string('source_key', 255)->nullable();
            $table->unsignedBigInteger('source_revision_id')->nullable();
            $table->string('source_snapshot_sha256', 64)->nullable();
            $table->string('workflow_status', 32)->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->bigInteger('allocated_minor')->default(0);
            $table->bigInteger('open_minor')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('cancels_invoice_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'id'], 'finance_invoices_owner_id_unique');
            $table->unique(
                ['user_id', 'id', 'document_series_id'],
                'finance_invoices_owner_id_series_unique',
            );
            $table->unique(
                ['user_id', 'document_series_id'],
                'finance_invoices_owner_series_unique',
            );
            $table->unique(['user_id', 'uuid'], 'finance_invoices_owner_uuid_unique');

            $table->foreign(
                ['user_id', 'document_series_id'],
                'finance_invoices_owner_series_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_document_series')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'document_series_id', 'current_revision_id'],
                'finance_invoices_owner_series_revision_foreign',
            )
                ->references(['user_id', 'document_series_id', 'id'])
                ->on('finance_document_revisions')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'cancels_invoice_id'],
                'finance_invoices_owner_cancellation_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_invoices')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['user_id', 'workflow_status', 'issue_date'],
                'finance_invoices_owner_workflow_issue_index',
            );
            $table->index(
                ['user_id', 'due_date', 'open_minor'],
                'finance_invoices_owner_due_open_index',
            );
        });
        $this->addInvoicePartialIndexes();
        $this->addInvoiceChecks();

        Schema::create('finance_invoice_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('series_key', 64);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_sequence')->default(1);
            $table->timestamps();

            $table->unique(
                ['user_id', 'series_key', 'year'],
                'finance_invoice_sequences_owner_series_year_unique',
            );
        });
        $this->addSequenceChecks();

        Schema::create('finance_invoice_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('invoice_id');
            $table->foreignId('document_series_id');
            $table->foreignId('document_revision_id');
            $table->string('kind', 32);
            $table->string('recipient', 320);
            $table->string('message_id', 255);
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error_code', 128)->nullable();
            $table->string('idempotency_key_hash', 64);
            $table->string('request_hash', 64);
            $table->timestamp('queued_at');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'id'], 'finance_invoice_deliveries_owner_id_unique');
            $table->unique(['user_id', 'uuid'], 'finance_invoice_deliveries_owner_uuid_unique');
            $table->unique(
                ['user_id', 'message_id'],
                'finance_invoice_deliveries_owner_message_unique',
            );
            $table->unique(
                ['user_id', 'kind', 'idempotency_key_hash'],
                'finance_invoice_deliveries_owner_kind_key_unique',
            );
            $table->foreign(
                ['user_id', 'invoice_id', 'document_series_id'],
                'finance_invoice_deliveries_owner_invoice_series_foreign',
            )
                ->references(['user_id', 'id', 'document_series_id'])
                ->on('finance_invoices')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'document_series_id', 'document_revision_id'],
                'finance_invoice_deliveries_owner_series_revision_foreign',
            )
                ->references(['user_id', 'document_series_id', 'id'])
                ->on('finance_document_revisions')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);

            $table->index(
                ['status', 'next_retry_at'],
                'finance_invoice_deliveries_status_retry_index',
            );
            $table->index(
                ['user_id', 'invoice_id', 'created_at'],
                'finance_invoice_deliveries_owner_invoice_created_index',
            );
        });
        $this->addDeliveryChecks();

        Schema::create('finance_idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 128);
            $table->string('key_hash', 64);
            $table->string('request_hash', 64);
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'operation', 'key_hash'],
                'finance_idempotency_records_owner_operation_key_unique',
            );
            $table->index(
                ['status', 'expires_at'],
                'finance_idempotency_records_status_expiry_index',
            );
        });
        $this->addIdempotencyChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_idempotency_records');
        Schema::dropIfExists('finance_invoice_deliveries');
        Schema::dropIfExists('finance_invoice_sequences');
        Schema::dropIfExists('finance_invoices');
        $this->dropRevisionCurrencyCheck();
    }

    private function addRevisionCurrencyCheck(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_document_revisions
                ADD CONSTRAINT finance_document_revisions_currency_check
                CHECK (currency ~ '^[A-Z]{3}$')
                SQL);

            return;
        }

        if (DB::table('finance_document_revisions')
            ->whereRaw("currency NOT GLOB '[A-Z][A-Z][A-Z]'")
            ->exists()) {
            throw new LogicException('Existing finance document revision has an invalid currency.');
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_document_revisions_currency_{$operation}_check
                BEFORE {$operation} ON finance_document_revisions
                WHEN NEW.currency NOT GLOB '[A-Z][A-Z][A-Z]'
                BEGIN
                    SELECT RAISE(ABORT, 'finance_document_revisions_currency_check');
                END
                SQL);
        }
    }

    private function dropRevisionCurrencyCheck(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_document_revisions
                DROP CONSTRAINT IF EXISTS finance_document_revisions_currency_check
                SQL);

            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared("DROP TRIGGER IF EXISTS finance_document_revisions_currency_{$operation}_check");
        }
    }

    private function addInvoicePartialIndexes(): void
    {
        $this->assertSupportedDriver();

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX finance_invoices_owner_source_unique
            ON finance_invoices (user_id, source_type, source_key)
            WHERE source_type IS NOT NULL AND source_key IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX finance_invoices_owner_number_unique
            ON finance_invoices (user_id, year, number)
            WHERE number IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX finance_invoices_owner_cancellation_unique
            ON finance_invoices (user_id, cancels_invoice_id)
            WHERE cancels_invoice_id IS NOT NULL
            SQL);
    }

    private function addInvoiceChecks(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_invoices
                ADD CONSTRAINT finance_invoices_kind_check
                    CHECK (kind IN ('invoice', 'credit_note')),
                ADD CONSTRAINT finance_invoices_workflow_check
                    CHECK (workflow_status IN ('draft', 'finalized', 'sent')),
                ADD CONSTRAINT finance_invoices_number_group_check
                    CHECK (
                        (number IS NULL AND year IS NULL AND sequence IS NULL)
                        OR (number IS NOT NULL AND year IS NOT NULL AND sequence IS NOT NULL)
                    ),
                ADD CONSTRAINT finance_invoices_sequence_check
                    CHECK (sequence IS NULL OR sequence >= 0),
                ADD CONSTRAINT finance_invoices_source_group_check
                    CHECK (
                        (
                            source_type IS NULL AND source_key IS NULL
                            AND source_revision_id IS NULL AND source_snapshot_sha256 IS NULL
                        )
                        OR (
                            source_type IS NOT NULL AND source_key IS NOT NULL
                            AND source_revision_id IS NOT NULL AND source_snapshot_sha256 IS NOT NULL
                            AND source_snapshot_sha256 ~ '^[0-9a-f]{64}$'
                        )
                    ),
                ADD CONSTRAINT finance_invoices_cancellation_not_self_check
                    CHECK (cancels_invoice_id IS NULL OR cancels_invoice_id <> id),
                ADD CONSTRAINT finance_invoices_version_check
                    CHECK (version >= 0)
                SQL);

            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_invoices_integrity_{$operation}_check
                BEFORE {$operation} ON finance_invoices
                WHEN
                    NEW.kind NOT IN ('invoice', 'credit_note')
                    OR NEW.workflow_status NOT IN ('draft', 'finalized', 'sent')
                    OR NOT (
                        (NEW.number IS NULL AND NEW.year IS NULL AND NEW.sequence IS NULL)
                        OR (NEW.number IS NOT NULL AND NEW.year IS NOT NULL AND NEW.sequence IS NOT NULL)
                    )
                    OR (NEW.sequence IS NOT NULL AND NEW.sequence < 0)
                    OR NOT (
                        (
                            NEW.source_type IS NULL AND NEW.source_key IS NULL
                            AND NEW.source_revision_id IS NULL AND NEW.source_snapshot_sha256 IS NULL
                        )
                        OR (
                            NEW.source_type IS NOT NULL AND NEW.source_key IS NOT NULL
                            AND NEW.source_revision_id IS NOT NULL AND NEW.source_snapshot_sha256 IS NOT NULL
                            AND length(NEW.source_snapshot_sha256) = 64
                            AND NEW.source_snapshot_sha256 NOT GLOB '*[^0-9a-f]*'
                        )
                    )
                    OR NEW.cancels_invoice_id = NEW.id
                    OR NEW.version < 0
                BEGIN
                    SELECT RAISE(ABORT, 'finance_invoices_integrity_check');
                END
                SQL);
        }
    }

    private function addSequenceChecks(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_invoice_sequences
                ADD CONSTRAINT finance_invoice_sequences_year_check
                    CHECK (year > 0),
                ADD CONSTRAINT finance_invoice_sequences_next_positive_check
                    CHECK (next_sequence > 0)
                SQL);

            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_invoice_sequences_integrity_{$operation}_check
                BEFORE {$operation} ON finance_invoice_sequences
                WHEN NEW.year <= 0 OR NEW.next_sequence <= 0
                BEGIN
                    SELECT RAISE(ABORT, 'finance_invoice_sequences_integrity_check');
                END
                SQL);
        }
    }

    private function addDeliveryChecks(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_invoice_deliveries
                ADD CONSTRAINT finance_invoice_deliveries_kind_check
                    CHECK (kind IN ('invoice', 'reminder')),
                ADD CONSTRAINT finance_invoice_deliveries_state_check
                    CHECK (status IN ('pending', 'sending', 'sent', 'failed', 'unknown')),
                ADD CONSTRAINT finance_invoice_deliveries_attempts_check
                    CHECK (attempts >= 0),
                ADD CONSTRAINT finance_invoice_deliveries_hashes_check
                    CHECK (
                        idempotency_key_hash ~ '^[0-9a-f]{64}$'
                        AND request_hash ~ '^[0-9a-f]{64}$'
                    )
                SQL);

            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_invoice_deliveries_integrity_{$operation}_check
                BEFORE {$operation} ON finance_invoice_deliveries
                WHEN
                    NEW.kind NOT IN ('invoice', 'reminder')
                    OR NEW.status NOT IN ('pending', 'sending', 'sent', 'failed', 'unknown')
                    OR NEW.attempts < 0
                    OR length(NEW.idempotency_key_hash) <> 64
                    OR NEW.idempotency_key_hash GLOB '*[^0-9a-f]*'
                    OR length(NEW.request_hash) <> 64
                    OR NEW.request_hash GLOB '*[^0-9a-f]*'
                BEGIN
                    SELECT RAISE(ABORT, 'finance_invoice_deliveries_integrity_check');
                END
                SQL);
        }
    }

    private function addIdempotencyChecks(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_idempotency_records
                ADD CONSTRAINT finance_idempotency_records_state_check
                    CHECK (status IN ('pending', 'completed', 'failed')),
                ADD CONSTRAINT finance_idempotency_records_hashes_check
                    CHECK (key_hash ~ '^[0-9a-f]{64}$' AND request_hash ~ '^[0-9a-f]{64}$'),
                ADD CONSTRAINT finance_idempotency_records_response_status_check
                    CHECK (response_status IS NULL OR response_status BETWEEN 100 AND 599)
                SQL);

            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_idempotency_records_integrity_{$operation}_check
                BEFORE {$operation} ON finance_idempotency_records
                WHEN
                    NEW.status NOT IN ('pending', 'completed', 'failed')
                    OR length(NEW.key_hash) <> 64
                    OR NEW.key_hash GLOB '*[^0-9a-f]*'
                    OR length(NEW.request_hash) <> 64
                    OR NEW.request_hash GLOB '*[^0-9a-f]*'
                    OR (NEW.response_status IS NOT NULL AND (NEW.response_status < 100 OR NEW.response_status > 599))
                BEGIN
                    SELECT RAISE(ABORT, 'finance_idempotency_records_integrity_check');
                END
                SQL);
        }
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
