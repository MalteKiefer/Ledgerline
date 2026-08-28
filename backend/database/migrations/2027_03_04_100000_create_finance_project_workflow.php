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
        $this->addFoundationReferenceKeys();
        $this->createProjects();
        $this->createWorkItems();
        $this->createTimeEntries();
        $this->createLedgerEntries();
        $this->createDocumentLinks();
        $this->createProjectNotes();
        $this->createProjectActivities();
        $this->createProjectOperations();
        $this->extendDocumentNotes();
    }

    public function down(): void
    {
        $this->removeDocumentNoteExtension();
        $this->removeDocumentSourceGuards();

        Schema::dropIfExists('finance_project_operations');
        Schema::dropIfExists('finance_project_activities');
        Schema::dropIfExists('finance_project_notes');
        Schema::dropIfExists('finance_project_document_links');
        Schema::dropIfExists('finance_project_ledger_entries');
        Schema::dropIfExists('finance_project_time_entries');
        Schema::dropIfExists('finance_project_work_items');
        Schema::dropIfExists('finance_project_records');

        Schema::table('finance_document_revisions', function (Blueprint $table): void {
            $table->dropUnique('finance_document_revisions_owner_id_unique');
        });
    }

    private function addFoundationReferenceKeys(): void
    {
        Schema::table('finance_document_revisions', function (Blueprint $table): void {
            $table->unique(['user_id', 'id'], 'finance_document_revisions_owner_id_unique');
        });
    }

    private function createProjects(): void
    {
        Schema::create('finance_project_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('parent_project_id')->nullable();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('name', 255);
            $table->enum('kind', ['business', 'private']);
            $table->enum('status', ['planned', 'active', 'on_hold', 'done', 'cancelled']);
            $table->string('partner_reference', 255)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->bigInteger('budget_minor')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'id'], 'finance_project_records_owner_id_unique');
            $table->unique(['user_id', 'uuid'], 'finance_project_records_owner_uuid_unique');
            $table->unique(
                ['user_id', 'source_type', 'source_id'],
                'finance_project_records_owner_source_unique',
            );
            $table->foreign(
                ['user_id', 'parent_project_id'],
                'finance_project_records_owner_parent_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_records')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['user_id', 'status', 'updated_at'],
                'finance_project_records_owner_status_updated_index',
            );
            $table->index(
                ['user_id', 'parent_project_id', 'updated_at'],
                'finance_project_records_owner_parent_updated_index',
            );
            $table->index(
                ['user_id', 'archived_at', 'updated_at'],
                'finance_project_records_owner_archive_updated_index',
            );
        });

        $this->addChecks('finance_project_records', [
            'finance_project_records_source_pair_check' => '(source_type IS NULL) = (source_id IS NULL)',
            'finance_project_records_parent_not_self_check' => 'parent_project_id IS NULL OR parent_project_id <> id',
            'finance_project_records_budget_nonnegative_check' => 'budget_minor IS NULL OR budget_minor >= 0',
            'finance_project_records_currency_check' => $this->currencyCheckExpression(),
            'finance_project_records_version_nonnegative_check' => 'version >= 0',
            'finance_project_records_actor_owner_check' => 'created_by IS NULL OR created_by = user_id',
        ]);
    }

    private function createWorkItems(): void
    {
        Schema::create('finance_project_work_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id');
            $table->uuid('uuid');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'in_progress', 'done']);
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->bigInteger('estimate_quantity_scaled')->nullable();
            $table->boolean('is_milestone')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('source_revision_id')->nullable();
            $table->unsignedInteger('source_line_index')->nullable();
            $table->string('product_reference', 255)->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'id'], 'finance_project_work_items_owner_id_unique');
            $table->unique(
                ['user_id', 'project_id', 'id'],
                'finance_project_work_items_owner_project_id_unique',
            );
            $table->unique(['user_id', 'uuid'], 'finance_project_work_items_owner_uuid_unique');
            $table->foreign(
                ['user_id', 'project_id'],
                'finance_project_work_items_owner_project_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_records')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'source_revision_id'],
                'finance_project_work_items_owner_revision_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_document_revisions')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['project_id', 'status', 'sort'],
                'finance_project_work_items_project_status_sort_index',
            );
            $table->unique(
                ['user_id', 'source_revision_id', 'source_line_index'],
                'finance_project_work_items_owner_source_line_unique',
            );
        });

        $this->addChecks('finance_project_work_items', [
            'finance_project_work_items_source_pair_check' => '(source_revision_id IS NULL) = (source_line_index IS NULL)',
            'finance_project_work_items_source_line_check' => 'source_line_index IS NULL OR source_line_index >= 0',
            'finance_project_work_items_estimate_positive_check' => 'estimate_quantity_scaled IS NULL OR estimate_quantity_scaled > 0',
            'finance_project_work_items_milestone_estimate_check' => 'is_milestone = false OR estimate_quantity_scaled IS NULL',
            'finance_project_work_items_version_nonnegative_check' => 'version >= 0',
            'finance_project_work_items_actor_owner_check' => 'created_by IS NULL OR created_by = user_id',
        ]);
    }

    private function createTimeEntries(): void
    {
        Schema::create('finance_project_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('work_item_id')->nullable();
            $table->uuid('uuid');
            $table->date('worked_on');
            $table->bigInteger('quantity_scaled');
            $table->text('description')->nullable();
            $table->boolean('billable')->default(true);
            $table->bigInteger('hourly_rate_minor')->nullable();
            $table->char('currency', 3);
            $table->string('invoice_target_reference', 255)->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'uuid'], 'finance_project_time_entries_owner_uuid_unique');
            $table->foreign(
                ['user_id', 'project_id'],
                'finance_project_time_entries_owner_project_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_records')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'project_id', 'work_item_id'],
                'finance_project_time_entries_owner_project_work_foreign',
            )
                ->references(['user_id', 'project_id', 'id'])
                ->on('finance_project_work_items')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['project_id', 'worked_on'],
                'finance_project_time_entries_project_worked_index',
            );
            $table->index(
                ['project_id', 'billable', 'invoiced_at'],
                'finance_project_time_entries_project_billing_index',
            );
        });

        $this->addChecks('finance_project_time_entries', [
            'finance_project_time_entries_quantity_nonzero_check' => 'quantity_scaled <> 0',
            'finance_project_time_entries_rate_nonnegative_check' => 'hourly_rate_minor IS NULL OR hourly_rate_minor >= 0',
            'finance_project_time_entries_invoice_pair_check' => '(invoice_target_reference IS NULL) = (invoiced_at IS NULL)',
            'finance_project_time_entries_currency_check' => $this->currencyCheckExpression(),
            'finance_project_time_entries_version_nonnegative_check' => 'version >= 0',
            'finance_project_time_entries_actor_owner_check' => 'created_by IS NULL OR created_by = user_id',
        ]);
    }

    private function createLedgerEntries(): void
    {
        Schema::create('finance_project_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id');
            $table->uuid('uuid');
            $table->enum('direction', ['out', 'in']);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->date('occurred_on')->nullable();
            $table->string('title', 255)->nullable();
            $table->text('note')->nullable();
            $table->string('category_reference', 255)->nullable();
            $table->string('payment_method_reference', 255)->nullable();
            $table->json('legacy_metadata')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'uuid'], 'finance_project_ledger_entries_owner_uuid_unique');
            $table->foreign(
                ['user_id', 'project_id'],
                'finance_project_ledger_entries_owner_project_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_records')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['project_id', 'occurred_on'],
                'finance_project_ledger_entries_project_occurred_index',
            );
            $table->index(
                ['project_id', 'direction', 'occurred_on'],
                'finance_project_ledger_entries_project_direction_date_index',
            );
        });

        $this->addChecks('finance_project_ledger_entries', [
            'finance_project_ledger_entries_amount_positive_check' => 'amount_minor > 0',
            'finance_project_ledger_entries_currency_check' => $this->currencyCheckExpression(),
            'finance_project_ledger_entries_version_nonnegative_check' => 'version >= 0',
            'finance_project_ledger_entries_actor_owner_check' => 'created_by IS NULL OR created_by = user_id',
        ]);
    }

    private function createDocumentLinks(): void
    {
        Schema::create('finance_project_document_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id');
            $table->enum('source_type', [
                'finance_series', 'legacy_invoice', 'file', 'gallery_photo',
                'finance_receipt', 'bank_transaction', 'bank_transaction_receipt',
            ]);
            $table->string('source_reference', 255);
            $table->foreignId('document_series_id')->nullable();
            $table->foreignId('pinned_revision_id')->nullable();
            $table->enum('role', [
                'source_quote', 'quote', 'invoice', 'payment', 'receipt', 'file', 'photo', 'other',
            ]);
            $table->json('metadata_snapshot');
            $table->foreignId('attached_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attached_at')->useCurrent();
            $table->foreignId('detached_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('detached_at')->nullable();

            $table->foreign(
                ['user_id', 'project_id'],
                'finance_project_document_links_owner_project_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_records')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'document_series_id'],
                'finance_project_document_links_owner_series_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_document_series')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'document_series_id', 'pinned_revision_id'],
                'finance_project_document_links_owner_revision_foreign',
            )
                ->references(['user_id', 'document_series_id', 'id'])
                ->on('finance_document_revisions')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['user_id', 'source_type', 'source_reference'],
                'finance_project_document_links_owner_source_index',
            );
            $table->index(
                ['project_id', 'role', 'attached_at'],
                'finance_project_document_links_project_role_attached_index',
            );
            $table->index(
                ['project_id', 'detached_at', 'attached_at'],
                'finance_project_document_links_project_state_time_index',
            );
        });

        $this->addChecks('finance_project_document_links', [
            'finance_project_document_links_source_pair_check' => "((source_type = 'finance_series') AND document_series_id IS NOT NULL) OR ((source_type <> 'finance_series') AND document_series_id IS NULL AND pinned_revision_id IS NULL)",
            'finance_project_document_links_detach_pair_check' => 'detached_by IS NULL OR detached_at IS NOT NULL',
            'finance_project_document_links_attached_owner_check' => 'attached_by IS NULL OR attached_by = user_id',
            'finance_project_document_links_detached_owner_check' => 'detached_by IS NULL OR detached_by = user_id',
        ]);
        $this->addDocumentSourceValidation();
        $this->addActiveDocumentLinkUniqueness();
    }

    private function createProjectNotes(): void
    {
        Schema::create('finance_project_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id');
            $table->enum('type', ['note', 'decision', 'call', 'email', 'meeting', 'correction']);
            $table->enum('visibility', ['internal', 'customer']);
            $table->text('body');
            $table->foreignId('supersedes_note_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['user_id', 'project_id', 'id'],
                'finance_project_notes_owner_project_id_unique',
            );
            $table->foreign(
                ['user_id', 'project_id'],
                'finance_project_notes_owner_project_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_records')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'project_id', 'supersedes_note_id'],
                'finance_project_notes_supersedes_foreign',
            )
                ->references(['user_id', 'project_id', 'id'])
                ->on('finance_project_notes')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['user_id', 'created_at'],
                'finance_project_notes_owner_created_index',
            );
            $table->index(
                ['project_id', 'type', 'created_at'],
                'finance_project_notes_project_type_created_index',
            );
            $table->index(
                ['project_id', 'visibility', 'created_at'],
                'finance_project_notes_project_visibility_created_index',
            );
        });

        $this->addChecks('finance_project_notes', [
            'finance_project_notes_correction_pair_check' => "(type = 'correction') = (supersedes_note_id IS NOT NULL)",
            'finance_project_notes_supersedes_not_self_check' => 'supersedes_note_id IS NULL OR supersedes_note_id <> id',
            'finance_project_notes_actor_owner_check' => 'created_by IS NULL OR created_by = user_id',
        ]);
    }

    private function createProjectActivities(): void
    {
        Schema::create('finance_project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id');
            $table->string('type', 64);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_reference', 255)->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(
                ['user_id', 'project_id'],
                'finance_project_activities_owner_project_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_records')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['user_id', 'occurred_at'],
                'finance_project_activities_owner_occurred_index',
            );
            $table->index(
                ['project_id', 'type', 'occurred_at'],
                'finance_project_activities_project_type_time_index',
            );
        });

        $this->addChecks('finance_project_activities', [
            'finance_project_activities_subject_pair_check' => '(subject_type IS NULL) = (subject_reference IS NULL)',
            'finance_project_activities_actor_owner_check' => 'created_by IS NULL OR created_by = user_id',
        ]);
    }

    private function createProjectOperations(): void
    {
        Schema::create('finance_project_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable();
            $table->string('operation', 64);
            $table->string('idempotency_key', 255);
            $table->char('request_sha256', 64);
            $table->enum('state', ['reserved', 'running', 'succeeded', 'failed']);
            $table->json('result')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            $table->unique(
                ['user_id', 'operation', 'idempotency_key'],
                'finance_project_operations_owner_operation_key_unique',
            );
            $table->foreign(
                ['user_id', 'project_id'],
                'finance_project_operations_owner_project_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_records')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['user_id', 'state', 'started_at'],
                'finance_project_operations_owner_state_started_index',
            );
            $table->index(
                ['project_id', 'started_at'],
                'finance_project_operations_project_started_index',
            );
        });

        $this->addChecks('finance_project_operations', [
            'finance_project_operations_sha256_length_check' => 'length(request_sha256) = 64',
        ]);
    }

    private function extendDocumentNotes(): void
    {
        $canonicalTypes = ['note', 'decision', 'call', 'email', 'meeting', 'correction'];

        Schema::table('finance_document_notes', function (Blueprint $table): void {
            $table->foreignId('supersedes_note_id')->nullable()->after('body');
            $table->unique(
                ['user_id', 'document_series_id', 'id'],
                'finance_document_notes_owner_series_id_unique',
            );
        });
        DB::statement("UPDATE finance_document_notes SET type = 'note' WHERE type = 'comment'");
        if (DB::getDriverName() === 'sqlite'
            && DB::table('finance_document_notes')->whereNotIn('type', $canonicalTypes)->exists()) {
            throw new LogicException('finance_document_notes contains an unsupported legacy type.');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION finance_document_notes_normalize_type()
                RETURNS trigger AS $$
                BEGIN
                    IF NEW.type = 'comment' THEN
                        NEW.type := 'note';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER finance_document_notes_normalize_type
                BEFORE INSERT OR UPDATE OF type ON finance_document_notes
                FOR EACH ROW EXECUTE FUNCTION finance_document_notes_normalize_type()
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE finance_document_notes
                ADD CONSTRAINT finance_document_notes_supersedes_foreign
                    FOREIGN KEY (user_id, document_series_id, supersedes_note_id)
                    REFERENCES finance_document_notes (user_id, document_series_id, id)
                    ON DELETE NO ACTION DEFERRABLE INITIALLY DEFERRED,
                ADD CONSTRAINT finance_document_notes_correction_pair_check
                    CHECK ((type = 'correction') = (supersedes_note_id IS NOT NULL)),
                ADD CONSTRAINT finance_document_notes_supersedes_not_self_check
                    CHECK (supersedes_note_id IS NULL OR supersedes_note_id <> id),
                ADD CONSTRAINT finance_document_notes_type_check
                    CHECK (type IN ('note', 'decision', 'call', 'email', 'meeting', 'correction'))
                SQL);

            return;
        }

        $this->assertSqlite();
        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_document_notes_type_{$operation}_check
                BEFORE {$operation} ON finance_document_notes
                WHEN NEW.type NOT IN ('note', 'decision', 'call', 'email', 'meeting', 'correction', 'comment')
                BEGIN
                    SELECT RAISE(ABORT, 'finance_document_notes_type_check');
                END
                SQL);
        }
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_document_notes_type_insert_normalize
            AFTER INSERT ON finance_document_notes
            WHEN NEW.type = 'comment'
            BEGIN
                UPDATE finance_document_notes SET type = 'note' WHERE id = NEW.id;
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_document_notes_type_update_normalize
            AFTER UPDATE OF type ON finance_document_notes
            WHEN NEW.type = 'comment'
            BEGIN
                UPDATE finance_document_notes SET type = 'note' WHERE id = NEW.id;
            END
            SQL);
        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_document_notes_correction_{$operation}_check
                BEFORE {$operation} ON finance_document_notes
                WHEN ((NEW.type = 'correction') != (NEW.supersedes_note_id IS NOT NULL))
                  OR NEW.supersedes_note_id = NEW.id
                  OR (
                    NEW.supersedes_note_id IS NOT NULL
                    AND NOT EXISTS (
                        SELECT 1 FROM finance_document_notes previous
                        WHERE previous.user_id = NEW.user_id
                          AND previous.document_series_id = NEW.document_series_id
                          AND previous.id = NEW.supersedes_note_id
                    )
                  )
                BEGIN
                    SELECT RAISE(ABORT, 'finance_document_notes_correction_pair_check');
                END
                SQL);
        }
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_document_notes_supersedes_parent_delete_restrict
            BEFORE DELETE ON finance_document_notes
            WHEN EXISTS (
                SELECT 1 FROM finance_document_notes correction
                WHERE correction.user_id = OLD.user_id
                  AND correction.document_series_id = OLD.document_series_id
                  AND correction.supersedes_note_id = OLD.id
            )
              AND EXISTS (SELECT 1 FROM users owner WHERE owner.id = OLD.user_id)
              AND EXISTS (
                SELECT 1 FROM finance_document_series series
                WHERE series.user_id = OLD.user_id
                  AND series.id = OLD.document_series_id
            )
            BEGIN
                SELECT RAISE(ABORT, 'finance_document_notes_supersedes_parent_delete_restrict');
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_document_notes_supersedes_parent_update_restrict
            BEFORE UPDATE OF id, user_id, document_series_id ON finance_document_notes
            WHEN (NEW.id <> OLD.id
                  OR NEW.user_id <> OLD.user_id
                  OR NEW.document_series_id <> OLD.document_series_id)
              AND EXISTS (
                SELECT 1 FROM finance_document_notes correction
                WHERE correction.user_id = OLD.user_id
                  AND correction.document_series_id = OLD.document_series_id
                  AND correction.supersedes_note_id = OLD.id
            )
            BEGIN
                SELECT RAISE(ABORT, 'finance_document_notes_supersedes_parent_update_restrict');
            END
            SQL);
    }

    private function removeDocumentNoteExtension(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS finance_document_notes_normalize_type ON finance_document_notes');
            DB::unprepared('DROP FUNCTION IF EXISTS finance_document_notes_normalize_type()');
            DB::statement(<<<'SQL'
                ALTER TABLE finance_document_notes
                DROP CONSTRAINT finance_document_notes_supersedes_foreign,
                DROP CONSTRAINT finance_document_notes_correction_pair_check,
                DROP CONSTRAINT finance_document_notes_supersedes_not_self_check,
                DROP CONSTRAINT finance_document_notes_type_check
                SQL);
        } elseif ($driver === 'sqlite') {
            foreach (['insert', 'update'] as $operation) {
                DB::unprepared("DROP TRIGGER IF EXISTS finance_document_notes_correction_{$operation}_check");
                DB::unprepared("DROP TRIGGER IF EXISTS finance_document_notes_type_{$operation}_check");
            }
            DB::unprepared('DROP TRIGGER IF EXISTS finance_document_notes_type_insert_normalize');
            DB::unprepared('DROP TRIGGER IF EXISTS finance_document_notes_type_update_normalize');
            DB::unprepared('DROP TRIGGER IF EXISTS finance_document_notes_supersedes_parent_delete_restrict');
            DB::unprepared('DROP TRIGGER IF EXISTS finance_document_notes_supersedes_parent_update_restrict');
        } else {
            throw new LogicException("Unsupported database driver: {$driver}");
        }

        Schema::table('finance_document_notes', function (Blueprint $table): void {
            $table->dropUnique('finance_document_notes_owner_series_id_unique');
            $table->dropColumn('supersedes_note_id');
        });
    }

    /** @param array<string, string> $constraints */
    private function addChecks(string $table, array $constraints): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $clauses = [];
            foreach ($constraints as $name => $expression) {
                $clauses[] = "ADD CONSTRAINT {$name} CHECK ({$expression})";
            }
            DB::statement("ALTER TABLE {$table} ".implode(', ', $clauses));

            return;
        }

        $this->assertSqlite();
        foreach ($constraints as $name => $expression) {
            $sqliteExpression = $this->sqliteCheckExpression($expression);
            foreach (['insert', 'update'] as $operation) {
                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$name}_{$operation}
                    BEFORE {$operation} ON {$table}
                    WHEN NOT ({$sqliteExpression})
                    BEGIN
                        SELECT RAISE(ABORT, '{$name}');
                    END
                    SQL);
            }
        }
    }

    private function sqliteCheckExpression(string $expression): string
    {
        $columns = [
            'amount_minor', 'attached_by', 'budget_minor', 'created_by', 'currency',
            'detached_at', 'detached_by', 'document_series_id', 'estimate_quantity_scaled',
            'hourly_rate_minor', 'id', 'invoiced_at', 'invoice_target_reference',
            'is_milestone', 'parent_project_id', 'pinned_revision_id', 'quantity_scaled',
            'request_sha256', 'source_id', 'source_line_index', 'source_reference',
            'source_revision_id', 'source_type', 'subject_reference', 'subject_type',
            'supersedes_note_id', 'type', 'user_id', 'version',
        ];

        foreach ($columns as $column) {
            $expression = preg_replace(
                '/(?<![.\w])'.preg_quote($column, '/').'(?!\w)/',
                "NEW.{$column}",
                $expression,
            ) ?? $expression;
        }

        return $expression;
    }

    private function currencyCheckExpression(): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "currency ~ '^[A-Z]{3}$'",
            'sqlite' => "currency GLOB '[A-Z][A-Z][A-Z]'",
            default => throw new LogicException('Unsupported database driver: '.DB::getDriverName()),
        };
    }

    private function addActiveDocumentLinkUniqueness(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX finance_project_document_links_active_unique
                ON finance_project_document_links
                    (user_id, project_id, source_type, source_reference, role)
                WHERE detached_at IS NULL
                SQL);

            return;
        }

        $this->assertSqlite();
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_project_document_links_active_insert_unique
            BEFORE INSERT ON finance_project_document_links
            WHEN NEW.detached_at IS NULL AND EXISTS (
                SELECT 1 FROM finance_project_document_links existing
                WHERE existing.user_id = NEW.user_id
                  AND existing.project_id = NEW.project_id
                  AND existing.source_type = NEW.source_type
                  AND existing.source_reference = NEW.source_reference
                  AND existing.role = NEW.role
                  AND existing.detached_at IS NULL
            )
            BEGIN
                SELECT RAISE(ABORT, 'finance_project_document_links_active_unique');
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_project_document_links_active_update_unique
            BEFORE UPDATE ON finance_project_document_links
            WHEN NEW.detached_at IS NULL AND EXISTS (
                SELECT 1 FROM finance_project_document_links existing
                WHERE existing.user_id = NEW.user_id
                  AND existing.project_id = NEW.project_id
                  AND existing.source_type = NEW.source_type
                  AND existing.source_reference = NEW.source_reference
                  AND existing.role = NEW.role
                  AND existing.detached_at IS NULL
                  AND existing.id <> NEW.id
            )
            BEGIN
                SELECT RAISE(ABORT, 'finance_project_document_links_active_unique');
            END
            SQL);
    }

    private function addDocumentSourceValidation(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION finance_project_document_links_validate_source()
                RETURNS trigger AS $$
                BEGIN
                    IF NEW.source_type = 'finance_series' AND NOT EXISTS (
                        SELECT 1 FROM finance_document_series series
                        WHERE series.user_id = NEW.user_id
                          AND series.id = NEW.document_series_id
                          AND series.uuid::text = NEW.source_reference
                    ) THEN
                        RAISE EXCEPTION 'finance_project_document_links_validate_source'
                            USING ERRCODE = '23514';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER finance_project_document_links_validate_source
                BEFORE INSERT OR UPDATE ON finance_project_document_links
                FOR EACH ROW EXECUTE FUNCTION finance_project_document_links_validate_source()
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION finance_project_document_series_guard_uuid()
                RETURNS trigger AS $$
                BEGIN
                    IF NEW.uuid IS DISTINCT FROM OLD.uuid AND EXISTS (
                        SELECT 1 FROM finance_project_document_links link
                        WHERE link.user_id = OLD.user_id
                          AND link.document_series_id = OLD.id
                    ) THEN
                        RAISE EXCEPTION 'finance_project_document_series_uuid_referenced'
                            USING ERRCODE = '23503';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER finance_project_document_series_guard_uuid
                BEFORE UPDATE OF uuid ON finance_document_series
                FOR EACH ROW EXECUTE FUNCTION finance_project_document_series_guard_uuid()
                SQL);

            return;
        }

        $this->assertSqlite();
        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_project_document_links_validate_source_{$operation}
                BEFORE {$operation} ON finance_project_document_links
                WHEN NEW.source_type = 'finance_series' AND NOT EXISTS (
                    SELECT 1 FROM finance_document_series series
                    WHERE series.user_id = NEW.user_id
                      AND series.id = NEW.document_series_id
                      AND series.uuid = NEW.source_reference
                )
                BEGIN
                    SELECT RAISE(ABORT, 'finance_project_document_links_validate_source');
                END
                SQL);
        }
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_project_document_series_guard_uuid
            BEFORE UPDATE OF uuid ON finance_document_series
            WHEN NEW.uuid <> OLD.uuid AND EXISTS (
                SELECT 1 FROM finance_project_document_links link
                WHERE link.user_id = OLD.user_id
                  AND link.document_series_id = OLD.id
            )
            BEGIN
                SELECT RAISE(ABORT, 'finance_project_document_series_uuid_referenced');
            END
            SQL);
    }

    private function removeDocumentSourceGuards(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS finance_project_document_series_guard_uuid ON finance_document_series');
            DB::unprepared('DROP FUNCTION IF EXISTS finance_project_document_series_guard_uuid()');
            DB::statement('DROP TRIGGER IF EXISTS finance_project_document_links_validate_source ON finance_project_document_links');
            DB::unprepared('DROP FUNCTION IF EXISTS finance_project_document_links_validate_source()');

            return;
        }

        $this->assertSqlite();
        DB::unprepared('DROP TRIGGER IF EXISTS finance_project_document_series_guard_uuid');
    }

    private function assertSqlite(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'sqlite') {
            throw new LogicException("Unsupported database driver: {$driver}");
        }
    }
};
