<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The password manager has been removed entirely. Purge the now-orphaned legacy
 * monolith `passwords` rows from the per-module store (these only ever served the
 * one-time dual-read migration into the — now dropped — sharded passwords store).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_stores')->where('module', 'passwords')->delete();
    }

    public function down(): void
    {
        // One-way teardown.
    }
};
