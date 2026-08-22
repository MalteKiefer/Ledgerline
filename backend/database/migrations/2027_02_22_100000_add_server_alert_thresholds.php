<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-server alert thresholds.
     *
     * 90 percent is right for a system disk and nonsense for a 20 TB archive
     * that lives at 95 and always has. A threshold nobody can move is one
     * people learn to ignore, and an ignored alert is worse than none.
     *
     * Nullable throughout: null means "use the built-in default", which keeps
     * every existing server behaving exactly as it did.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->unsignedTinyInteger('disk_alert_pct')->nullable()->after('monitor_ports');
            $table->unsignedTinyInteger('mem_alert_pct')->nullable()->after('disk_alert_pct');
            $table->unsignedSmallInteger('temp_alert_c')->nullable()->after('mem_alert_pct');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn(['disk_alert_pct', 'mem_alert_pct', 'temp_alert_c']);
        });
    }
};
