<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Contacts module has been removed entirely. Purge the now-orphaned sealed
 * contact records from the per-module store.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_stores')->where('module', 'contacts')->delete();
    }

    public function down(): void
    {
        // One-way teardown.
    }
};
