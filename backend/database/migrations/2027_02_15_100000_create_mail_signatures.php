<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('html')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'name']);
        });

        Schema::create('mail_account_signatures', function (Blueprint $table): void {
            $table->foreignId('mail_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_signature_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->primary(['mail_account_id', 'mail_signature_id']);
            $table->index(['mail_account_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_account_signatures');
        Schema::dropIfExists('mail_signatures');
    }
};
