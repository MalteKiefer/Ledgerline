<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the outcome of a non-destructive integrity + dry-run-restore check on
 * a completed backup run, so the operator can confirm a backup is actually
 * restorable without spending it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_runs', function (Blueprint $table): void {
            $table->timestamp('verified_at')->nullable()->after('finished_at');
            $table->string('verify_status')->nullable()->after('verified_at');
            $table->text('verify_message')->nullable()->after('verify_status');
        });
    }

    public function down(): void
    {
        Schema::table('backup_runs', function (Blueprint $table): void {
            $table->dropColumn(['verified_at', 'verify_status', 'verify_message']);
        });
    }
};
