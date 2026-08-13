<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Finance): imported bank-statement lines, one row
 * per booking on an account (payment_method). date/amount + vat_cat stay
 * plaintext for stats/filtering; the free-text booking details (counterparty,
 * purpose, IBAN, …) and the attached receipts list carry an `encrypted` cast.
 * `sig` is a dedup signature. Rows can be linked to an invoice and/or a project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 14, 2);         // plaintext (stats/filter)
            $table->string('vat_cat', 16)->nullable(); // 19|16|7|0|private
            $table->string('sig', 80)->nullable();     // dedup signature
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('invoice_number', 64)->nullable();
            $table->foreignId('finance_project_id')->nullable()->constrained('finance_projects')->nullOnDelete();
            // Encrypted booking details:
            $table->text('counterparty')->nullable();
            $table->text('counterparty_iban')->nullable();
            $table->text('bic')->nullable();
            $table->text('purpose')->nullable();
            $table->text('booking_text')->nullable();
            $table->text('eref')->nullable();
            $table->longText('receipts')->nullable(); // encrypted:array — [{id,blob_path,name,mime,kind,category,tags,contactId,partnerId,vat,eigenbeleg,locked,trashed,sig}]
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'payment_method_id', 'date']);
            $table->index(['user_id', 'sig']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
