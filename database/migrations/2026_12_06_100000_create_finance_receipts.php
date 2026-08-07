<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone finance receipts ("Fremdbelege"): a receipt document that does NOT
 * require a bank transaction. Previously a receipt could only live embedded in
 * a bank_transactions.receipts[] JSON array, so a user with no imported bank
 * statement had no way to upload a receipt for documentation. This gives every
 * receipt a first-class home; an optional bank_transaction_id links it to a
 * booking when one exists (informational, nullOnDelete). Bytes live plaintext on
 * the files disk under invoices/{uuid}; blob_path is server-owned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
            $table->foreignId('finance_project_id')->nullable()->constrained('finance_projects')->nullOnDelete();
            $table->string('blob_path', 255);
            $table->string('name', 500);
            $table->string('mime', 255)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sig', 128)->nullable();
            $table->string('kind', 24)->default('receipt');
            $table->string('category', 160)->nullable();
            $table->json('tags')->nullable();
            $table->string('vat', 16)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->text('ocr')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['user_id', 'deleted_at']);
            $table->index(['user_id', 'bank_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_receipts');
    }
};
