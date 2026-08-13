<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mark invoices that were imported from an existing PDF. Imported (historical)
 * invoices may be deleted even though they are finalised, so mistaken imports
 * can be removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('imported_at')->nullable()->after('finalized_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('imported_at');
        });
    }
};
