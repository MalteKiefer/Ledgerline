<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared password-Tresor container. One row per tresor; the uuid primary key is
 * generated client-side (creating hook). The server knows only who owns it and
 * when it was created — no name, labels or member count are stored in cleartext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_vaults', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_vaults');
    }
};
