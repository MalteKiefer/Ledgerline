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
        Schema::create('finance_document_series', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('document_type', 32);
            $table->string('status', 32);
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'id'], 'finance_document_series_owner_id_unique');
            $table->unique(['user_id', 'uuid'], 'finance_document_series_owner_uuid_unique');
            $table->unique(
                ['user_id', 'source_type', 'source_id'],
                'finance_document_series_owner_source_unique',
            );
            $table->index(
                ['user_id', 'document_type', 'status'],
                'finance_document_series_owner_type_status_index',
            );
        });
        $this->addSourcePairConstraint();

        Schema::create('finance_document_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_series_id');
            $table->unsignedInteger('revision_number');
            $table->foreignId('previous_revision_id')->nullable();
            $table->string('status', 32);
            $table->json('snapshot');
            $table->bigInteger('net_minor');
            $table->bigInteger('vat_minor');
            $table->bigInteger('gross_minor');
            $table->char('currency', 3);
            $table->text('change_reason')->nullable();
            $table->string('pdf_path', 2048)->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['user_id', 'document_series_id'], 'finance_document_revisions_owner_series_foreign')
                ->references(['user_id', 'id'])
                ->on('finance_document_series')
                ->restrictOnDelete();
            $table->unique(
                ['document_series_id', 'id'],
                'finance_document_revisions_series_id_unique',
            );
            $table->unique(
                ['user_id', 'document_series_id', 'id'],
                'finance_document_revisions_owner_series_id_unique',
            );
            $table->foreign(
                ['document_series_id', 'previous_revision_id'],
                'finance_document_revisions_previous_foreign',
            )
                ->references(['document_series_id', 'id'])
                ->on('finance_document_revisions')
                ->restrictOnDelete();
            $table->unique(
                ['document_series_id', 'revision_number'],
                'finance_document_revisions_series_number_unique',
            );
            $table->index(['user_id', 'status'], 'finance_document_revisions_owner_status_index');
            $table->index(
                ['document_series_id', 'created_at'],
                'finance_document_revisions_series_created_index',
            );
        });
        $this->addRevisionCheckConstraints();

        Schema::create('finance_document_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_series_id');
            $table->foreignId('document_revision_id')->nullable();
            $table->string('type', 64);
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['user_id', 'document_series_id'], 'finance_document_activities_owner_series_foreign')
                ->references(['user_id', 'id'])
                ->on('finance_document_series')
                ->cascadeOnDelete();
            $table->foreign(
                ['user_id', 'document_series_id', 'document_revision_id'],
                'finance_document_activities_owner_series_revision_foreign',
            )
                ->references(['user_id', 'document_series_id', 'id'])
                ->on('finance_document_revisions')
                ->restrictOnDelete();
            $table->index(
                ['user_id', 'created_at'],
                'finance_document_activities_owner_created_index',
            );
            $table->index(
                ['document_series_id', 'created_at'],
                'finance_document_activities_series_created_index',
            );
        });

        Schema::create('finance_document_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_series_id');
            $table->foreignId('document_revision_id')->nullable();
            $table->string('type', 64);
            $table->enum('visibility', ['internal', 'customer']);
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['user_id', 'document_series_id'], 'finance_document_notes_owner_series_foreign')
                ->references(['user_id', 'id'])
                ->on('finance_document_series')
                ->cascadeOnDelete();
            $table->foreign(
                ['user_id', 'document_series_id', 'document_revision_id'],
                'finance_document_notes_owner_series_revision_foreign',
            )
                ->references(['user_id', 'document_series_id', 'id'])
                ->on('finance_document_revisions')
                ->restrictOnDelete();
            $table->index(
                ['user_id', 'created_at'],
                'finance_document_notes_owner_created_index',
            );
            $table->index(
                ['document_series_id', 'created_at'],
                'finance_document_notes_series_created_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_document_notes');
        Schema::dropIfExists('finance_document_activities');
        Schema::dropIfExists('finance_document_revisions');
        Schema::dropIfExists('finance_document_series');
    }

    private function addSourcePairConstraint(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_document_series
                ADD CONSTRAINT finance_document_series_source_pair_check
                CHECK ((source_type IS NULL) = (source_id IS NULL))
                SQL);

            return;
        }

        if ($driver !== 'sqlite') {
            throw new LogicException("Unsupported database driver: {$driver}");
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_document_series_source_pair_{$operation}_check
                BEFORE {$operation} ON finance_document_series
                WHEN ((NEW.source_type IS NULL) != (NEW.source_id IS NULL))
                BEGIN
                    SELECT RAISE(ABORT, 'finance_document_series_source_pair_check');
                END
                SQL);
        }
    }

    private function addRevisionCheckConstraints(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_document_revisions
                ADD CONSTRAINT finance_document_revisions_number_positive_check
                    CHECK (revision_number > 0),
                ADD CONSTRAINT finance_document_revisions_previous_not_self_check
                    CHECK (previous_revision_id IS NULL OR previous_revision_id <> id)
                SQL);

            return;
        }

        if ($driver !== 'sqlite') {
            throw new LogicException("Unsupported database driver: {$driver}");
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_document_revisions_number_positive_{$operation}_check
                BEFORE {$operation} ON finance_document_revisions
                WHEN NEW.revision_number <= 0
                BEGIN
                    SELECT RAISE(ABORT, 'finance_document_revisions_number_positive_check');
                END
                SQL);
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_document_revisions_previous_not_self_{$operation}_check
                BEFORE {$operation} ON finance_document_revisions
                WHEN NEW.previous_revision_id = NEW.id
                BEGIN
                    SELECT RAISE(ABORT, 'finance_document_revisions_previous_not_self_check');
                END
                SQL);
        }
    }
};
