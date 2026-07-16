<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice number template (placeholders like YYYY/MM/NNNN) plus the next running
 * sequence number, so an owner who already issued invoices this year can start
 * the counter wherever they left off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('invoice_number_format')->nullable()->after('invoice_number_padding');
            $table->unsignedInteger('invoice_next_number')->nullable()->after('invoice_number_format');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['invoice_number_format', 'invoice_next_number']);
        });
    }
};
