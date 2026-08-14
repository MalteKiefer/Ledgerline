<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance categories gain an optional Sachkonto/account number (e.g. a SKR03/04
 * chart-of-accounts code such as "4930" or "4930 - Bürobedarf") — the owner enters
 * it once per category to match their own accountant's chart of accounts; the app
 * never invents a number itself. Additive + nullable only — no data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_categories', function (Blueprint $table): void {
            $table->string('account_no', 40)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('finance_categories', function (Blueprint $table): void {
            $table->dropColumn('account_no');
        });
    }
};
