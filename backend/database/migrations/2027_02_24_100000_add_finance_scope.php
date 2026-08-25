<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business vs private, across accounts, bookings and receipts.
 *
 * Everything recorded so far belongs to the business, so the account column
 * defaults to `business` and every existing row lands there without a backfill.
 * Bookings and receipts are nullable on purpose: null means "whatever the
 * account is", so opening a private account does not require touching the
 * thousands of rows that hang off it. An explicit value overrides — a private
 * purchase paid from the business card, or a business expense that happened to
 * go through the private account.
 *
 * This is NOT the same as `bank_transactions.vat_cat = 'private'`. That marks an
 * owner withdrawal or deposit ON A BUSINESS ACCOUNT — it stays in the books and
 * is merely excluded from VAT and expenses. A private-scope row is outside the
 * books entirely and never reaches a tax report.
 *
 * `finance_projects` already carries the same distinction as `kind`, and keeps
 * that older name; the reports treat both identically.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_methods', 'scope')) {
            Schema::table('payment_methods', function (Blueprint $table): void {
                $table->string('scope', 16)->default('business')->after('name');
            });
        }
        if (! Schema::hasColumn('bank_transactions', 'scope')) {
            Schema::table('bank_transactions', function (Blueprint $table): void {
                // Nullable = inherit from the account.
                $table->string('scope', 16)->nullable()->after('vat_cat');
            });
        }
        if (! Schema::hasColumn('finance_receipts', 'scope')) {
            Schema::table('finance_receipts', function (Blueprint $table): void {
                // `kind` is taken (receipt vs eigenbeleg), hence `scope`.
                $table->string('scope', 16)->nullable()->after('kind');
            });
        }
    }

    public function down(): void
    {
        foreach (['payment_methods', 'bank_transactions', 'finance_receipts'] as $table) {
            if (Schema::hasColumn($table, 'scope')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->dropColumn('scope');
                });
            }
        }
    }
};
