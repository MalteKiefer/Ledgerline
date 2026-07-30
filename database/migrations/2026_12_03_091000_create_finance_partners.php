<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Finance): business partners (Geschäftspartner).
 * One row per partner. name/category/kind + url/logo/note stay plaintext so the
 * server can list/search. Contact PII (address/email/phone/vat_id) plus the list
 * of contact people carry an `encrypted` cast → kept out of DB dumps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 300);            // plaintext
            $table->string('category', 120)->nullable(); // plaintext
            $table->string('kind', 16)->nullable();
            $table->text('url')->nullable();
            $table->text('logo')->nullable();
            $table->text('note')->nullable();
            // Encrypted PII:
            $table->text('address')->nullable();    // encrypted cast
            $table->text('email')->nullable();      // encrypted cast
            $table->text('phone')->nullable();      // encrypted cast
            $table->text('vat_id')->nullable();     // encrypted cast
            $table->longText('contacts')->nullable(); // encrypted:array — [{id,name,email,phone,role}]
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_partners');
    }
};
