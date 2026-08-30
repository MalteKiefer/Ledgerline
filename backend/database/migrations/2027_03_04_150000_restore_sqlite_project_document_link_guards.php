<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array CHECKS = [
        'finance_project_document_links_source_type_check' => "NEW.source_type IN ('finance_series', 'legacy_invoice', 'file', 'gallery_photo', 'finance_receipt', 'bank_transaction', 'bank_transaction_receipt')",
        'finance_project_document_links_role_check' => "NEW.role IN ('source_quote', 'quote', 'invoice', 'payment', 'receipt', 'file', 'photo', 'other')",
        'finance_project_document_links_source_pair_check' => "((NEW.source_type = 'finance_series') AND NEW.document_series_id IS NOT NULL) OR ((NEW.source_type <> 'finance_series') AND NEW.document_series_id IS NULL AND NEW.pinned_revision_id IS NULL)",
        'finance_project_document_links_detach_pair_check' => 'NEW.detached_by IS NULL OR NEW.detached_at IS NOT NULL',
        'finance_project_document_links_attached_owner_check' => 'NEW.attached_by IS NULL OR NEW.attached_by = NEW.user_id',
        'finance_project_document_links_detached_owner_check' => 'NEW.detached_by IS NULL OR NEW.detached_by = NEW.user_id',
    ];

    private const array OPERATIONS = ['insert', 'update'];

    public function up(): void
    {
        if ($this->isPostgres()) {
            return;
        }
        $this->assertSqlite();

        foreach (self::CHECKS as $name => $expression) {
            foreach (self::OPERATIONS as $operation) {
                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$name}_{$operation}
                    BEFORE {$operation} ON finance_project_document_links
                    WHEN NOT ({$expression})
                    BEGIN
                        SELECT RAISE(ABORT, '{$name}');
                    END
                    SQL);
            }
        }

        foreach (self::OPERATIONS as $operation) {
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

    public function down(): void
    {
        if ($this->isPostgres()) {
            return;
        }
        $this->assertSqlite();

        foreach (array_keys(self::CHECKS) as $name) {
            foreach (self::OPERATIONS as $operation) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$name}_{$operation}");
            }
        }
        foreach (self::OPERATIONS as $operation) {
            DB::unprepared("DROP TRIGGER IF EXISTS finance_project_document_links_validate_source_{$operation}");
        }
        DB::unprepared('DROP TRIGGER IF EXISTS finance_project_document_links_active_insert_unique');
        DB::unprepared('DROP TRIGGER IF EXISTS finance_project_document_links_active_update_unique');
    }

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    private function assertSqlite(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'sqlite') {
            throw new LogicException("Unsupported database driver: {$driver}");
        }
    }
};
