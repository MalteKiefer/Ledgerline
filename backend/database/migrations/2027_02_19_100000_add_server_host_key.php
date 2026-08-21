<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the pinned host key itself, not only its fingerprint.
 *
 * The pin is enforced by handing OpenSSH a known_hosts entry, and that needs the
 * whole key — a fingerprint alone can only be compared after the fact, which is
 * exactly the window a pin is meant to close.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->text('host_key')->nullable()->after('host_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn('host_key');
        });
    }
};
