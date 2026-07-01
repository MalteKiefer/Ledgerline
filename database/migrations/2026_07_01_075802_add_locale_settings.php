<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a per-user preferred locale, and invoice presentation settings on the
 * company profile: tax display mode (per line or per invoice) and paper size.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 5)->nullable()->after('email');
        });

        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->string('tax_display', 10)->default('line')->after('default_tax_rate');
            $table->string('paper_size', 10)->default('A4')->after('tax_display');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });

        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->dropColumn(['tax_display', 'paper_size']);
        });
    }
};
