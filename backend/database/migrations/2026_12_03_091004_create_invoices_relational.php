<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Finance): invoices. One row per invoice. The
 * number/seq/year are plaintext (GoBD: gapless, unique per year — the server
 * assigns them atomically on finalisation). Money columns (gross/net/vat) are
 * plaintext decimals so the server can drive VAT-return + revenue stats. The
 * customer snapshot, line items, free-text note and GoBD correction history
 * carry an `encrypted` cast. The rendered PDF lives plaintext on the file disk
 * at pdf_path (server-set, never mass-assigned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('number', 64)->nullable();   // plaintext (GoBD)
            $table->unsignedInteger('seq')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('status', 16)->default('draft'); // draft|sent|paid
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 8)->default('EUR');
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('gross', 14, 2)->nullable();
            $table->decimal('net', 14, 2)->nullable();
            $table->decimal('vat', 14, 2)->nullable();
            $table->boolean('imported')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_account', 200)->nullable();
            $table->foreignId('partner_id')->nullable()->constrained('finance_partners')->nullOnDelete();
            $table->string('pdf_path', 255)->nullable(); // plaintext blob on disk
            // Encrypted:
            $table->longText('customer')->nullable();  // encrypted:array — {name,address,email,vatId,attn,partnerId}
            $table->longText('lines')->nullable();      // encrypted:array — [{desc,qty,unit,unitPrice,amount,vatRate}]
            $table->text('note')->nullable();           // encrypted
            $table->longText('versions')->nullable();   // encrypted:array — GoBD correction history
            $table->unsignedInteger('version_seq')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'year']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
