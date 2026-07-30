<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Finance migrated to plaintext-relational (invoices / bank_transactions /
 * payment_methods / finance_partners / finance_projects / finance_categories +
 * plaintext PDF/receipt files on disk). Drop the zero-knowledge sharded invoices
 * store + blob ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('invoices_blobs');
        Schema::dropIfExists('invoices_store');
    }

    public function down(): void
    {
        // One-way teardown.
    }
};
