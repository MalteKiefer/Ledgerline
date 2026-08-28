<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MAX_MINOR = 99_999_999_999_999;

    public function up(): void
    {
        Schema::create('finance_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamp('received_at');
            $table->string('reference', 255)->nullable();
            $table->string('counterparty', 255)->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->string('source_type', 64)->nullable();
            $table->string('source_key', 255)->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'id'], 'finance_payments_owner_id_unique');
            $table->unique(['user_id', 'uuid'], 'finance_payments_owner_uuid_unique');
            $table->index(['user_id', 'received_at'], 'finance_payments_owner_received_index');
            $table->index(
                ['user_id', 'payment_method_id', 'received_at'],
                'finance_payments_owner_method_received_index',
            );
        });
        $this->addPaymentSourceIndex();
        $this->addPaymentChecks();

        Schema::create('finance_payment_allocation_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id');
            $table->string('idempotency_key_hash', 64);
            $table->string('request_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['user_id', 'id', 'payment_id'],
                'finance_payment_batches_owner_id_payment_unique',
            );
            $table->unique(
                ['user_id', 'idempotency_key_hash'],
                'finance_payment_batches_owner_key_unique',
            );
            $table->foreign(
                ['user_id', 'payment_id'],
                'finance_payment_batches_owner_payment_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_payments')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['user_id', 'payment_id', 'created_at'],
                'finance_payment_batches_owner_payment_created_index',
            );
        });
        $this->addBatchChecks();

        Schema::create('finance_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allocation_batch_id');
            $table->foreignId('payment_id');
            $table->foreignId('invoice_id');
            $table->bigInteger('amount_minor');
            $table->foreignId('reverses_allocation_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['user_id', 'id', 'payment_id', 'invoice_id'],
                'finance_payment_allocations_owner_context_unique',
            );
            $table->unique(
                ['reverses_allocation_id'],
                'finance_payment_allocations_reversal_unique',
            );
            $table->foreign(
                ['user_id', 'allocation_batch_id', 'payment_id'],
                'finance_payment_allocations_owner_batch_payment_foreign',
            )
                ->references(['user_id', 'id', 'payment_id'])
                ->on('finance_payment_allocation_batches')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'payment_id'],
                'finance_payment_allocations_owner_payment_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_payments')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'invoice_id'],
                'finance_payment_allocations_owner_invoice_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_invoices')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'reverses_allocation_id', 'payment_id', 'invoice_id'],
                'finance_payment_allocations_owner_reversal_foreign',
            )
                ->references(['user_id', 'id', 'payment_id', 'invoice_id'])
                ->on('finance_payment_allocations')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->index(
                ['user_id', 'invoice_id', 'created_at'],
                'finance_payment_allocations_owner_invoice_created_index',
            );
            $table->index(
                ['user_id', 'payment_id', 'created_at'],
                'finance_payment_allocations_owner_payment_created_index',
            );
        });
        $this->addAllocationChecksAndGuards();
        $this->addAllocatedContextGuards();
        $this->addLedgerImmutabilityGuards();
    }

    public function down(): void
    {
        $driver = $this->assertSupportedDriver();
        $this->dropAllocatedContextGuards($driver);

        Schema::dropIfExists('finance_payment_allocations');
        Schema::dropIfExists('finance_payment_allocation_batches');
        Schema::dropIfExists('finance_payments');

        if ($driver === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS finance_payment_allocation_guard()');
            DB::unprepared('DROP FUNCTION IF EXISTS finance_payment_allocated_context_guard()');
            DB::unprepared('DROP FUNCTION IF EXISTS finance_payment_ledger_immutable_guard()');
        }
    }

    private function addPaymentSourceIndex(): void
    {
        $this->assertSupportedDriver();

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX finance_payments_owner_source_unique
            ON finance_payments (user_id, source_type, source_key)
            WHERE source_type IS NOT NULL AND source_key IS NOT NULL
            SQL);
    }

    private function addPaymentChecks(): void
    {
        $driver = $this->assertSupportedDriver();
        $maxMinor = self::MAX_MINOR;

        if ($driver === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE finance_payments
                ADD CONSTRAINT finance_payments_amount_check
                    CHECK (amount_minor <> 0 AND amount_minor BETWEEN -{$maxMinor} AND {$maxMinor}),
                ADD CONSTRAINT finance_payments_currency_check
                    CHECK (currency ~ '^[A-Z]{3}$'),
                ADD CONSTRAINT finance_payments_source_pair_check
                    CHECK ((source_type IS NULL) = (source_key IS NULL)),
                ADD CONSTRAINT finance_payments_version_check
                    CHECK (version >= 0)
                SQL);

            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER finance_payments_integrity_{$operation}_check
                BEFORE {$operation} ON finance_payments
                WHEN
                    NEW.amount_minor = 0
                    OR NEW.amount_minor < -{$maxMinor}
                    OR NEW.amount_minor > {$maxMinor}
                    OR NEW.currency NOT GLOB '[A-Z][A-Z][A-Z]'
                    OR ((NEW.source_type IS NULL) != (NEW.source_key IS NULL))
                    OR NEW.version < 0
                BEGIN
                    SELECT RAISE(ABORT, 'finance_payments_integrity_check');
                END
                SQL);
        }
    }

    private function addBatchChecks(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE finance_payment_allocation_batches
                ADD CONSTRAINT finance_payment_batches_hashes_check
                    CHECK (
                        idempotency_key_hash ~ '^[0-9a-f]{64}$'
                        AND request_hash ~ '^[0-9a-f]{64}$'
                    )
                SQL);

            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_payment_batches_integrity_insert_check
            BEFORE INSERT ON finance_payment_allocation_batches
            WHEN
                length(NEW.idempotency_key_hash) <> 64
                OR NEW.idempotency_key_hash GLOB '*[^0-9a-f]*'
                OR length(NEW.request_hash) <> 64
                OR NEW.request_hash GLOB '*[^0-9a-f]*'
            BEGIN
                SELECT RAISE(ABORT, 'finance_payment_batches_integrity_check');
            END
            SQL);
    }

    private function addAllocationChecksAndGuards(): void
    {
        $driver = $this->assertSupportedDriver();
        $maxMinor = self::MAX_MINOR;

        if ($driver === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE finance_payment_allocations
                ADD CONSTRAINT finance_payment_allocations_amount_check
                    CHECK (amount_minor <> 0 AND amount_minor BETWEEN -{$maxMinor} AND {$maxMinor}),
                ADD CONSTRAINT finance_payment_allocations_reversal_not_self_check
                    CHECK (reverses_allocation_id IS NULL OR reverses_allocation_id <> id)
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION finance_payment_allocation_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                DECLARE
                    payment_amount bigint;
                    payment_currency char(3);
                    invoice_gross bigint;
                    invoice_currency char(3);
                    original_amount bigint;
                    original_reversal_id bigint;
                BEGIN
                    SELECT amount_minor, currency
                    INTO payment_amount, payment_currency
                    FROM finance_payments
                    WHERE user_id = NEW.user_id AND id = NEW.payment_id;

                    SELECT revision.gross_minor, revision.currency
                    INTO invoice_gross, invoice_currency
                    FROM finance_invoices AS invoice
                    INNER JOIN finance_document_revisions AS revision
                        ON revision.user_id = invoice.user_id
                        AND revision.document_series_id = invoice.document_series_id
                        AND revision.id = invoice.current_revision_id
                    WHERE invoice.user_id = NEW.user_id AND invoice.id = NEW.invoice_id;

                    IF payment_amount IS NULL OR invoice_gross IS NULL THEN
                        RAISE EXCEPTION 'finance_payment_allocation_owner_context_check'
                            USING ERRCODE = '23503';
                    END IF;

                    IF payment_currency <> invoice_currency THEN
                        RAISE EXCEPTION 'finance_payment_allocation_currency_check'
                            USING ERRCODE = '23514';
                    END IF;

                    IF NEW.reverses_allocation_id IS NULL THEN
                        IF sign(NEW.amount_minor) <> sign(payment_amount)
                            OR sign(NEW.amount_minor) <> sign(invoice_gross) THEN
                            RAISE EXCEPTION 'finance_payment_allocation_sign_check'
                                USING ERRCODE = '23514';
                        END IF;

                        RETURN NEW;
                    END IF;

                    SELECT amount_minor, reverses_allocation_id
                    INTO original_amount, original_reversal_id
                    FROM finance_payment_allocations
                    WHERE user_id = NEW.user_id
                        AND id = NEW.reverses_allocation_id
                        AND payment_id = NEW.payment_id
                        AND invoice_id = NEW.invoice_id;

                    IF original_amount IS NULL
                        OR original_reversal_id IS NOT NULL
                        OR NEW.amount_minor <> -original_amount THEN
                        RAISE EXCEPTION 'finance_payment_allocation_reversal_check'
                            USING ERRCODE = '23514';
                    END IF;

                    RETURN NEW;
                END;
                $$
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER finance_payment_allocation_guard
                BEFORE INSERT ON finance_payment_allocations
                FOR EACH ROW
                EXECUTE FUNCTION finance_payment_allocation_guard()
                SQL);

            return;
        }

        DB::unprepared(<<<SQL
            CREATE TRIGGER finance_payment_allocations_integrity_insert_check
            BEFORE INSERT ON finance_payment_allocations
            WHEN
                NEW.amount_minor = 0
                OR NEW.amount_minor < -{$maxMinor}
                OR NEW.amount_minor > {$maxMinor}
                OR NEW.reverses_allocation_id = NEW.id
            BEGIN
                SELECT RAISE(ABORT, 'finance_payment_allocations_integrity_check');
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_payment_allocation_guard
            BEFORE INSERT ON finance_payment_allocations
            WHEN
                NOT EXISTS (
                    SELECT 1
                    FROM finance_payments AS payment
                    WHERE payment.user_id = NEW.user_id
                        AND payment.id = NEW.payment_id
                )
                OR NOT EXISTS (
                    SELECT 1
                    FROM finance_invoices AS invoice
                    INNER JOIN finance_document_revisions AS revision
                        ON revision.user_id = invoice.user_id
                        AND revision.document_series_id = invoice.document_series_id
                        AND revision.id = invoice.current_revision_id
                    WHERE invoice.user_id = NEW.user_id
                        AND invoice.id = NEW.invoice_id
                )
                OR (
                    SELECT payment.currency <> revision.currency
                    FROM finance_payments AS payment
                    CROSS JOIN finance_invoices AS invoice
                    INNER JOIN finance_document_revisions AS revision
                        ON revision.user_id = invoice.user_id
                        AND revision.document_series_id = invoice.document_series_id
                        AND revision.id = invoice.current_revision_id
                    WHERE payment.user_id = NEW.user_id
                        AND payment.id = NEW.payment_id
                        AND invoice.user_id = NEW.user_id
                        AND invoice.id = NEW.invoice_id
                )
                OR (
                    NEW.reverses_allocation_id IS NULL
                    AND (
                        (NEW.amount_minor > 0) <> (
                            SELECT payment.amount_minor > 0
                            FROM finance_payments AS payment
                            WHERE payment.user_id = NEW.user_id AND payment.id = NEW.payment_id
                        )
                        OR (NEW.amount_minor > 0) <> (
                            SELECT revision.gross_minor > 0
                            FROM finance_invoices AS invoice
                            INNER JOIN finance_document_revisions AS revision
                                ON revision.user_id = invoice.user_id
                                AND revision.document_series_id = invoice.document_series_id
                                AND revision.id = invoice.current_revision_id
                            WHERE invoice.user_id = NEW.user_id AND invoice.id = NEW.invoice_id
                        )
                    )
                )
                OR (
                    NEW.reverses_allocation_id IS NOT NULL
                    AND NOT EXISTS (
                        SELECT 1
                        FROM finance_payment_allocations AS original
                        WHERE original.user_id = NEW.user_id
                            AND original.id = NEW.reverses_allocation_id
                            AND original.payment_id = NEW.payment_id
                            AND original.invoice_id = NEW.invoice_id
                            AND original.reverses_allocation_id IS NULL
                            AND NEW.amount_minor = -original.amount_minor
                    )
                )
            BEGIN
                SELECT RAISE(ABORT, 'finance_payment_allocation_guard');
            END
            SQL);
    }

    private function addLedgerImmutabilityGuards(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION finance_payment_ledger_immutable_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM users WHERE id = OLD.user_id) THEN
                        RAISE EXCEPTION 'finance_payment_ledger_immutable_guard'
                            USING ERRCODE = '23514';
                    END IF;

                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;

                    RETURN NEW;
                END;
                $$
                SQL);

            foreach (['finance_payment_allocation_batches', 'finance_payment_allocations'] as $table) {
                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$table}_immutable
                    BEFORE UPDATE OR DELETE ON {$table}
                    FOR EACH ROW
                    EXECUTE FUNCTION finance_payment_ledger_immutable_guard()
                    SQL);
            }

            return;
        }

        foreach (['finance_payment_allocation_batches', 'finance_payment_allocations'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$table}_immutable_{$operation}
                    BEFORE {$operation} ON {$table}
                    WHEN EXISTS (SELECT 1 FROM users WHERE id = OLD.user_id)
                    BEGIN
                        SELECT RAISE(ABORT, 'finance_payment_ledger_immutable_guard');
                    END
                    SQL);
            }
        }
    }

    private function addAllocatedContextGuards(): void
    {
        $driver = $this->assertSupportedDriver();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION finance_payment_allocated_context_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF TG_TABLE_NAME = 'finance_payments'
                        AND (
                            to_jsonb(NEW) ->> 'amount_minor' IS DISTINCT FROM to_jsonb(OLD) ->> 'amount_minor'
                            OR to_jsonb(NEW) ->> 'currency' IS DISTINCT FROM to_jsonb(OLD) ->> 'currency'
                        )
                        AND EXISTS (
                            SELECT 1 FROM finance_payment_allocations
                            WHERE user_id = OLD.user_id AND payment_id = OLD.id
                        ) THEN
                        RAISE EXCEPTION 'finance_payment_allocated_payment_context_guard'
                            USING ERRCODE = '23514';
                    END IF;

                    IF TG_TABLE_NAME = 'finance_invoices'
                        AND (
                            to_jsonb(NEW) ->> 'user_id' IS DISTINCT FROM to_jsonb(OLD) ->> 'user_id'
                            OR to_jsonb(NEW) ->> 'document_series_id' IS DISTINCT FROM to_jsonb(OLD) ->> 'document_series_id'
                            OR to_jsonb(NEW) ->> 'current_revision_id' IS DISTINCT FROM to_jsonb(OLD) ->> 'current_revision_id'
                            OR to_jsonb(NEW) ->> 'kind' IS DISTINCT FROM to_jsonb(OLD) ->> 'kind'
                        )
                        AND EXISTS (
                            SELECT 1 FROM finance_payment_allocations
                            WHERE user_id = OLD.user_id AND invoice_id = OLD.id
                        ) THEN
                        RAISE EXCEPTION 'finance_payment_allocated_invoice_context_guard'
                            USING ERRCODE = '23514';
                    END IF;

                    IF TG_TABLE_NAME = 'finance_document_revisions'
                        AND (
                            to_jsonb(NEW) ->> 'gross_minor' IS DISTINCT FROM to_jsonb(OLD) ->> 'gross_minor'
                            OR to_jsonb(NEW) ->> 'currency' IS DISTINCT FROM to_jsonb(OLD) ->> 'currency'
                        )
                        AND EXISTS (
                            SELECT 1
                            FROM finance_invoices AS invoice
                            INNER JOIN finance_payment_allocations AS allocation
                                ON allocation.user_id = invoice.user_id
                                AND allocation.invoice_id = invoice.id
                            WHERE invoice.user_id = OLD.user_id
                                AND invoice.document_series_id = OLD.document_series_id
                                AND invoice.current_revision_id = OLD.id
                        ) THEN
                        RAISE EXCEPTION 'finance_payment_allocated_revision_context_guard'
                            USING ERRCODE = '23514';
                    END IF;

                    RETURN NEW;
                END;
                $$
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER finance_payments_allocated_context_immutable
                BEFORE UPDATE OF amount_minor, currency ON finance_payments
                FOR EACH ROW
                EXECUTE FUNCTION finance_payment_allocated_context_guard()
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER finance_invoices_allocated_context_immutable
                BEFORE UPDATE OF user_id, document_series_id, current_revision_id, kind ON finance_invoices
                FOR EACH ROW
                EXECUTE FUNCTION finance_payment_allocated_context_guard()
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER finance_document_revisions_allocated_context_immutable
                BEFORE UPDATE OF gross_minor, currency ON finance_document_revisions
                FOR EACH ROW
                EXECUTE FUNCTION finance_payment_allocated_context_guard()
                SQL);

            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_payments_allocated_context_immutable
            BEFORE UPDATE OF amount_minor, currency ON finance_payments
            WHEN
                (NEW.amount_minor <> OLD.amount_minor OR NEW.currency <> OLD.currency)
                AND EXISTS (
                    SELECT 1 FROM finance_payment_allocations
                    WHERE user_id = OLD.user_id AND payment_id = OLD.id
                )
            BEGIN
                SELECT RAISE(ABORT, 'finance_payment_allocated_payment_context_guard');
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_invoices_allocated_context_immutable
            BEFORE UPDATE OF user_id, document_series_id, current_revision_id, kind ON finance_invoices
            WHEN
                (
                    NEW.user_id <> OLD.user_id
                    OR NEW.document_series_id <> OLD.document_series_id
                    OR NEW.current_revision_id <> OLD.current_revision_id
                    OR NEW.kind <> OLD.kind
                )
                AND EXISTS (
                    SELECT 1 FROM finance_payment_allocations
                    WHERE user_id = OLD.user_id AND invoice_id = OLD.id
                )
            BEGIN
                SELECT RAISE(ABORT, 'finance_payment_allocated_invoice_context_guard');
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finance_document_revisions_allocated_context_immutable
            BEFORE UPDATE OF gross_minor, currency ON finance_document_revisions
            WHEN
                (NEW.gross_minor <> OLD.gross_minor OR NEW.currency <> OLD.currency)
                AND EXISTS (
                    SELECT 1
                    FROM finance_invoices AS invoice
                    INNER JOIN finance_payment_allocations AS allocation
                        ON allocation.user_id = invoice.user_id
                        AND allocation.invoice_id = invoice.id
                    WHERE invoice.user_id = OLD.user_id
                        AND invoice.document_series_id = OLD.document_series_id
                        AND invoice.current_revision_id = OLD.id
                )
            BEGIN
                SELECT RAISE(ABORT, 'finance_payment_allocated_revision_context_guard');
            END
            SQL);
    }

    private function dropAllocatedContextGuards(string $driver): void
    {
        if ($driver === 'pgsql') {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS finance_payments_allocated_context_immutable ON finance_payments',
            );
            DB::unprepared(
                'DROP TRIGGER IF EXISTS finance_invoices_allocated_context_immutable ON finance_invoices',
            );
            DB::unprepared(
                'DROP TRIGGER IF EXISTS finance_document_revisions_allocated_context_immutable ON finance_document_revisions',
            );

            return;
        }

        foreach ([
            'finance_payments_allocated_context_immutable',
            'finance_invoices_allocated_context_immutable',
            'finance_document_revisions_allocated_context_immutable',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
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
