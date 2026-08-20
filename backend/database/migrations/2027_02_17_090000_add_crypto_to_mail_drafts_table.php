<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_drafts', function (Blueprint $table): void {
            $table->string('crypto_mode', 16)->default('none');
            $table->string('crypto_type', 8)->nullable();
            $table->foreignId('signing_key_id')->nullable()->constrained('mail_pgp_keys')->nullOnDelete();
            $table->json('recipient_key_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mail_drafts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signing_key_id');
            $table->dropColumn(['crypto_mode', 'crypto_type', 'recipient_key_ids']);
        });
    }
};
