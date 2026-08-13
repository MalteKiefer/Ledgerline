<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The zero-knowledge password manager has been removed entirely (the owner keeps
 * their credentials in 1Password). Drop its sharded sealed store + blob ledger —
 * no data bridge (see the GRUNDSATZ-PIVOT register entry).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('passwords_blobs');
        Schema::dropIfExists('passwords_store');
    }

    public function down(): void
    {
        // One-way teardown: the ZK passwords stores are not recreated on rollback.
    }
};
