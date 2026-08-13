<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-user / per-group module allow-lists (JSON array of module keys, null = all).
// Non-secret metadata; drives the `module` permission gate. Additive, nullable.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('modules')->nullable()->after('max_connected_devices');
        });
        Schema::table('groups', function (Blueprint $table): void {
            $table->json('modules')->nullable()->after('shareable');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('modules'));
        Schema::table('groups', fn (Blueprint $t) => $t->dropColumn('modules'));
    }
};
