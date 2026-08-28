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
        Schema::create('finance_quote_series', function (Blueprint $table): void {
            $table->unsignedBigInteger('document_series_id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable();
            $table->foreignId('current_revision_id')->nullable();
            $table->string('number', 64)->nullable();
            $table->unsignedSmallInteger('sequence_year')->nullable();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['user_id', 'document_series_id'],
                'finance_quote_series_owner_document_unique',
            );
            $table->foreign(
                ['user_id', 'document_series_id'],
                'finance_quote_series_owner_document_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_document_series')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'document_series_id', 'current_revision_id'],
                'finance_quote_series_current_revision_foreign',
            )
                ->references(['user_id', 'document_series_id', 'id'])
                ->on('finance_document_revisions')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign('partner_id', 'finance_quote_series_partner_id_foreign')
                ->references('id')
                ->on('finance_partners')
                ->nullOnDelete();
            $table->unique(
                ['user_id', 'sequence_year', 'sequence_number'],
                'finance_quote_series_owner_sequence_unique',
            );
            $table->index(
                ['user_id', 'published_at'],
                'finance_quote_series_owner_published_index',
            );
        });

        Schema::create('finance_quote_drafts', function (Blueprint $table): void {
            $table->unsignedBigInteger('document_series_id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('based_on_revision_id')->nullable();
            $table->json('payload');
            $table->bigInteger('net_minor');
            $table->bigInteger('vat_minor');
            $table->bigInteger('gross_minor');
            $table->char('currency', 3);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(
                ['user_id', 'document_series_id'],
                'finance_quote_drafts_owner_series_foreign',
            )
                ->references(['user_id', 'document_series_id'])
                ->on('finance_quote_series')
                ->cascadeOnDelete();
            $table->foreign(
                ['user_id', 'document_series_id', 'based_on_revision_id'],
                'finance_quote_drafts_based_revision_foreign',
            )
                ->references(['user_id', 'document_series_id', 'id'])
                ->on('finance_document_revisions')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
        });

        Schema::create('finance_quote_number_sequences', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_sequence');

            $table->unique(
                ['user_id', 'year'],
                'finance_quote_number_sequences_owner_year_unique',
            );
        });

        Schema::create('finance_quote_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('document_series_id')->nullable();
            $table->string('operation', 64);
            $table->string('idempotency_key', 255);
            $table->char('request_sha256', 64);
            $table->enum('state', ['reserved', 'running', 'succeeded', 'failed']);
            $table->json('result')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            $table->foreign(
                ['user_id', 'document_series_id'],
                'finance_quote_operations_owner_series_foreign',
            )
                ->references(['user_id', 'document_series_id'])
                ->on('finance_quote_series')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->unique(
                ['user_id', 'operation', 'idempotency_key'],
                'finance_quote_operations_owner_operation_key_unique',
            );
            $table->index(
                ['user_id', 'document_series_id', 'state'],
                'finance_quote_operations_owner_series_state_index',
            );
        });

        Schema::create('finance_quote_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('document_series_id');
            $table->unsignedBigInteger('document_revision_id');
            $table->string('recipient', 320);
            $table->string('recipient_domain', 253);
            $table->string('message_id', 255);
            $table->enum('state', ['queued', 'sending', 'sent', 'failed']);
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error_code', 64)->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->foreign(
                ['user_id', 'document_series_id'],
                'finance_quote_deliveries_owner_series_foreign',
            )
                ->references(['user_id', 'document_series_id'])
                ->on('finance_quote_series')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'document_series_id', 'document_revision_id'],
                'finance_quote_deliveries_owner_series_revision_foreign',
            )
                ->references(['user_id', 'document_series_id', 'id'])
                ->on('finance_document_revisions')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->unique(
                ['user_id', 'message_id'],
                'finance_quote_deliveries_owner_message_unique',
            );
            $table->index(
                ['user_id', 'document_series_id', 'queued_at'],
                'finance_quote_deliveries_owner_series_queued_index',
            );
        });

        Schema::create('finance_quote_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('document_series_id');
            $table->unsignedBigInteger('source_revision_id');
            $table->enum('target_type', ['invoice']);
            $table->string('target_reference', 255);
            $table->foreignId('target_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(
                ['user_id', 'document_series_id'],
                'finance_quote_conversions_owner_series_foreign',
            )
                ->references(['user_id', 'document_series_id'])
                ->on('finance_quote_series')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'document_series_id', 'source_revision_id'],
                'finance_quote_conversions_owner_series_revision_foreign',
            )
                ->references(['user_id', 'document_series_id', 'id'])
                ->on('finance_document_revisions')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign('target_id', 'finance_quote_conversions_target_id_foreign')
                ->references('id')
                ->on('invoices')
                ->nullOnDelete();
            $table->unique(
                ['user_id', 'source_revision_id', 'target_type'],
                'finance_quote_conversions_owner_source_type_unique',
            );
            $table->index(
                ['user_id', 'document_series_id', 'created_at'],
                'finance_quote_conversions_owner_series_created_index',
            );
        });

        $this->addCheckConstraints();
        $this->addOwnerGuards();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS finance_quote_document_series_type_guard ON finance_document_series');
            DB::statement('DROP TRIGGER IF EXISTS finance_quote_partner_owner_update_guard ON finance_partners');
            DB::statement('DROP TRIGGER IF EXISTS finance_quote_invoice_owner_update_guard ON invoices');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS finance_quote_document_series_type_guard');
            DB::statement('DROP TRIGGER IF EXISTS finance_quote_partner_owner_update_guard');
            DB::statement('DROP TRIGGER IF EXISTS finance_quote_invoice_owner_update_guard');
        }

        Schema::dropIfExists('finance_quote_conversions');
        Schema::dropIfExists('finance_quote_deliveries');
        Schema::dropIfExists('finance_quote_operations');
        Schema::dropIfExists('finance_quote_number_sequences');
        Schema::dropIfExists('finance_quote_drafts');
        Schema::dropIfExists('finance_quote_series');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS finance_quote_conversions_target_owner_guard()');
            DB::statement('DROP FUNCTION IF EXISTS finance_quote_series_partner_owner_guard()');
            DB::statement('DROP FUNCTION IF EXISTS finance_quote_series_document_type_guard()');
            DB::statement('DROP FUNCTION IF EXISTS finance_quote_document_series_type_guard()');
            DB::statement('DROP FUNCTION IF EXISTS finance_quote_partner_owner_update_guard()');
            DB::statement('DROP FUNCTION IF EXISTS finance_quote_invoice_owner_update_guard()');
        }
    }

    private function addCheckConstraints(): void
    {
        $this->addChecks('finance_quote_series', [
            'finance_quote_series_version_nonnegative_check' => [
                'version >= 0',
                'NEW.version < 0',
            ],
            'finance_quote_series_number_tuple_check' => [
                'CASE WHEN number IS NULL '
                    .'THEN sequence_year IS NULL AND sequence_number IS NULL '
                    .'ELSE length(trim(number)) > 0 AND sequence_year IS NOT NULL '
                    .'AND sequence_year > 0 AND sequence_number IS NOT NULL AND sequence_number > 0 END',
                'NOT (CASE WHEN NEW.number IS NULL '
                    .'THEN NEW.sequence_year IS NULL AND NEW.sequence_number IS NULL '
                    .'ELSE length(trim(NEW.number)) > 0 AND NEW.sequence_year IS NOT NULL '
                    .'AND NEW.sequence_year > 0 AND NEW.sequence_number IS NOT NULL '
                    .'AND NEW.sequence_number > 0 END)',
            ],
            'finance_quote_series_decision_check' => [
                'accepted_at IS NULL OR declined_at IS NULL',
                'NEW.accepted_at IS NOT NULL AND NEW.declined_at IS NOT NULL',
            ],
        ]);
        $this->addChecks('finance_quote_number_sequences', [
            'finance_quote_number_sequences_positive_check' => [
                'year > 0 AND next_sequence > 0',
                'NEW.year <= 0 OR NEW.next_sequence <= 0',
            ],
        ]);
        $this->addChecks('finance_quote_operations', [
            'finance_quote_operations_request_hash_check' => [
                'length(request_sha256) = 64',
                'length(NEW.request_sha256) <> 64',
            ],
        ]);
        $this->addChecks('finance_quote_deliveries', [
            'finance_quote_deliveries_attempts_check' => [
                'attempts >= 0',
                'NEW.attempts < 0',
            ],
        ]);
    }

    /**
     * @param  array<string, array{string, string}>  $checks
     */
    private function addChecks(string $table, array $checks): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $constraints = [];
            foreach ($checks as $name => [$condition]) {
                $constraints[] = "ADD CONSTRAINT {$name} CHECK ({$condition})";
            }
            DB::statement("ALTER TABLE {$table} ".implode(', ', $constraints));

            return;
        }

        if ($driver !== 'sqlite') {
            throw new LogicException("Unsupported database driver: {$driver}");
        }

        foreach ($checks as $name => [, $invalidCondition]) {
            foreach (['insert', 'update'] as $operation) {
                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$name}_{$operation}
                    BEFORE {$operation} ON {$table}
                    WHEN {$invalidCondition}
                    BEGIN
                        SELECT RAISE(ABORT, '{$name}');
                    END
                    SQL);
            }
        }
    }

    private function addOwnerGuards(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION finance_quote_series_document_type_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM finance_document_series
                        WHERE id = NEW.document_series_id
                            AND user_id = NEW.user_id
                            AND document_type = 'quote'
                    ) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            MESSAGE = 'finance_quote_series_document_type_check',
                            CONSTRAINT = 'finance_quote_series_document_type_check';
                    END IF;
                    RETURN NEW;
                END
                $$
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER finance_quote_series_document_type_guard
                BEFORE INSERT OR UPDATE OF user_id, document_series_id ON finance_quote_series
                FOR EACH ROW EXECUTE FUNCTION finance_quote_series_document_type_guard()
                SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION finance_quote_document_series_type_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF NEW.document_type <> 'quote' AND EXISTS (
                        SELECT 1 FROM finance_quote_series
                        WHERE document_series_id = NEW.id
                    ) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            MESSAGE = 'finance_quote_series_document_type_check',
                            CONSTRAINT = 'finance_quote_series_document_type_check';
                    END IF;
                    RETURN NEW;
                END
                $$
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER finance_quote_document_series_type_guard
                BEFORE UPDATE OF document_type ON finance_document_series
                FOR EACH ROW EXECUTE FUNCTION finance_quote_document_series_type_guard()
                SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION finance_quote_series_partner_owner_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF NEW.partner_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM finance_partners
                        WHERE id = NEW.partner_id AND user_id = NEW.user_id
                    ) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23503',
                            MESSAGE = 'finance_quote_series_partner_owner_foreign',
                            CONSTRAINT = 'finance_quote_series_partner_owner_foreign';
                    END IF;
                    RETURN NEW;
                END
                $$
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER finance_quote_series_partner_owner_guard
                BEFORE INSERT OR UPDATE OF user_id, partner_id ON finance_quote_series
                FOR EACH ROW EXECUTE FUNCTION finance_quote_series_partner_owner_guard()
                SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION finance_quote_partner_owner_update_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM finance_quote_series
                        WHERE partner_id = NEW.id AND user_id <> NEW.user_id
                    ) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23503',
                            MESSAGE = 'finance_quote_series_partner_owner_foreign',
                            CONSTRAINT = 'finance_quote_series_partner_owner_foreign';
                    END IF;
                    RETURN NEW;
                END
                $$
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER finance_quote_partner_owner_update_guard
                BEFORE UPDATE OF user_id ON finance_partners
                FOR EACH ROW EXECUTE FUNCTION finance_quote_partner_owner_update_guard()
                SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION finance_quote_conversions_target_owner_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF NEW.target_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM invoices
                        WHERE id = NEW.target_id AND user_id = NEW.user_id
                    ) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23503',
                            MESSAGE = 'finance_quote_conversions_target_owner_foreign',
                            CONSTRAINT = 'finance_quote_conversions_target_owner_foreign';
                    END IF;
                    RETURN NEW;
                END
                $$
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER finance_quote_conversions_target_owner_guard
                BEFORE INSERT OR UPDATE OF user_id, target_id ON finance_quote_conversions
                FOR EACH ROW EXECUTE FUNCTION finance_quote_conversions_target_owner_guard()
                SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION finance_quote_invoice_owner_update_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM finance_quote_conversions
                        WHERE target_id = NEW.id AND user_id <> NEW.user_id
                    ) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23503',
                            MESSAGE = 'finance_quote_conversions_target_owner_foreign',
                            CONSTRAINT = 'finance_quote_conversions_target_owner_foreign';
                    END IF;
                    RETURN NEW;
                END
                $$
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER finance_quote_invoice_owner_update_guard
                BEFORE UPDATE OF user_id ON invoices
                FOR EACH ROW EXECUTE FUNCTION finance_quote_invoice_owner_update_guard()
                SQL);

            return;
        }

        if ($driver !== 'sqlite') {
            throw new LogicException("Unsupported database driver: {$driver}");
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_quote_document_series_type_guard
            BEFORE UPDATE OF document_type ON finance_document_series
            WHEN NEW.document_type <> 'quote' AND EXISTS (
                SELECT 1 FROM finance_quote_series
                WHERE document_series_id = NEW.id
            )
            BEGIN
                SELECT RAISE(ABORT, 'finance_quote_series_document_type_check');
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_quote_partner_owner_update_guard
            BEFORE UPDATE OF user_id ON finance_partners
            WHEN EXISTS (
                SELECT 1 FROM finance_quote_series
                WHERE partner_id = NEW.id AND user_id <> NEW.user_id
            )
            BEGIN
                SELECT RAISE(ABORT, 'finance_quote_series_partner_owner_foreign');
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_quote_invoice_owner_update_guard
            BEFORE UPDATE OF user_id ON invoices
            WHEN EXISTS (
                SELECT 1 FROM finance_quote_conversions
                WHERE target_id = NEW.id AND user_id <> NEW.user_id
            )
            BEGIN
                SELECT RAISE(ABORT, 'finance_quote_conversions_target_owner_foreign');
            END
            SQL);

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_quote_series_document_type_guard_{$operation}
                BEFORE {$operation} ON finance_quote_series
                WHEN NOT EXISTS (
                    SELECT 1 FROM finance_document_series
                    WHERE id = NEW.document_series_id
                        AND user_id = NEW.user_id
                        AND document_type = 'quote'
                )
                BEGIN
                    SELECT RAISE(ABORT, 'finance_quote_series_document_type_check');
                END
                SQL);
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_quote_series_partner_owner_guard_{$operation}
                BEFORE {$operation} ON finance_quote_series
                WHEN NEW.partner_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM finance_partners
                    WHERE id = NEW.partner_id AND user_id = NEW.user_id
                )
                BEGIN
                    SELECT RAISE(ABORT, 'finance_quote_series_partner_owner_foreign');
                END
                SQL);
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_quote_conversions_target_owner_guard_{$operation}
                BEFORE {$operation} ON finance_quote_conversions
                WHEN NEW.target_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM invoices
                    WHERE id = NEW.target_id AND user_id = NEW.user_id
                )
                BEGIN
                    SELECT RAISE(ABORT, 'finance_quote_conversions_target_owner_foreign');
                END
                SQL);
        }
    }
};
