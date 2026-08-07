<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Finance): business partners (Geschäftspartner).
 * One row per partner. All columns are PLAINTEXT at rest (encryption removed in
 * v1.516.0 — confidentiality-at-rest is an infra concern: LUKS + encrypted
 * backups). name/category/kind + url/logo/note are listed/searched server-side;
 * the contact PII (address/email/phone/vat_id) and the contact-people list are
 * likewise stored plaintext (the FinancePartner model casts them as string /
 * `array`, NOT `encrypted`).
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
            // Contact PII — PLAINTEXT (no `encrypted` cast on the model):
            $table->text('address')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->text('vat_id')->nullable();
            $table->longText('contacts')->nullable(); // array cast — [{id,name,email,phone,role}]
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
