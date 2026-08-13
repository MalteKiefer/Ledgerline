<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the invoice number zero-padding width, so the generated number format can
 * match an existing series (e.g. "2026-004" uses a pad of 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->unsignedTinyInteger('invoice_number_pad')->default(4)->after('invoice_number_next');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->dropColumn('invoice_number_pad');
        });
    }
};
