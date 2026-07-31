<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the plaintext-relational modules. PostgreSQL does not
 * auto-index the referencing column of a foreign key, and several hot list/sort
 * paths (bank_transactions by date, invoices by issue_date, the gallery timeline
 * COALESCE(taken_at, created_at) sort) had no supporting index. All b-tree
 * indexes below are portable across pgsql + sqlite; the functional gallery index
 * is pgsql-only (sqlite is the test driver and falls back to a filesort).
 */
return new class extends Migration
{
    public function up(): void
    {
        // A/B/C — bank_transactions: FK lookups + cascade + global date-desc list
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->index('invoice_id', 'bank_transactions_invoice_id_idx');
            $table->index('finance_project_id', 'bank_transactions_project_id_idx');
            $table->index(['user_id', 'date'], 'bank_transactions_user_date_idx');
        });

        // D/E — invoices: partner FK + issue-date list
        Schema::table('invoices', function (Blueprint $table): void {
            $table->index('partner_id', 'invoices_partner_id_idx');
            $table->index(['user_id', 'issue_date'], 'invoices_user_issue_date_idx');
        });

        // F — gallery pivot: photo->album lookups + photo force-delete cascade
        Schema::table('gallery_album_photo', function (Blueprint $table): void {
            $table->index('gallery_photo_id', 'gallery_album_photo_photo_id_idx');
        });

        // H — files: updated_at-desc listing
        Schema::table('files', function (Blueprint $table): void {
            $table->index(['user_id', 'updated_at'], 'files_user_updated_idx');
        });

        // G — gallery timeline sort. Postgres: functional + partial (active rows
        // only) index matching COALESCE(taken_at, created_at) DESC. sqlite skips
        // it (test-only; degrades to filesort).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE INDEX gallery_photos_timeline_idx ON gallery_photos '
                .'(user_id, COALESCE(taken_at, created_at) DESC, id DESC) '
                .'WHERE deleted_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropIndex('bank_transactions_invoice_id_idx');
            $table->dropIndex('bank_transactions_project_id_idx');
            $table->dropIndex('bank_transactions_user_date_idx');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_partner_id_idx');
            $table->dropIndex('invoices_user_issue_date_idx');
        });
        Schema::table('gallery_album_photo', function (Blueprint $table): void {
            $table->dropIndex('gallery_album_photo_photo_id_idx');
        });
        Schema::table('files', function (Blueprint $table): void {
            $table->dropIndex('files_user_updated_idx');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS gallery_photos_timeline_idx');
        }
    }
};
