<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot Phase 1: notes migrated to the plaintext-relational `notes` table.
 * Drop the zero-knowledge sharded notes store + blob ledger — no data bridge
 * (the owner keeps local copies; see the GRUNDSATZ-PIVOT register entry).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('notes_blobs');
        Schema::dropIfExists('notes_store');
    }

    public function down(): void
    {
        // One-way teardown: the ZK notes stores are not recreated on rollback.
    }
};
