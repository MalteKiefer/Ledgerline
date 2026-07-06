<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// First-class, reusable HTML signatures (user-owned, unlimited). Identities
// reference one via signature_id; the legacy per-identity `signature` text
// column stays for back-compat but new work uses signatures.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->longText('html')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::table('mail_identities', function (Blueprint $table) {
            $table->foreignId('signature_id')->nullable()->after('signature')
                ->constrained('mail_signatures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mail_identities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signature_id');
        });
        Schema::dropIfExists('mail_signatures');
    }
};
