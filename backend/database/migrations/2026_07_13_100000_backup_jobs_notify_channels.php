<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_jobs', function (Blueprint $table): void {
            $table->json('notify_channels')->nullable()->after('notify');
        });

        // Backfill: the old single channel becomes a one-element list ('none' → []).
        foreach (DB::table('backup_jobs')->get(['id', 'notify']) as $job) {
            $channels = ($job->notify && $job->notify !== 'none') ? [$job->notify] : [];
            DB::table('backup_jobs')->where('id', $job->id)->update([
                'notify_channels' => json_encode($channels),
            ]);
        }

        Schema::table('backup_jobs', function (Blueprint $table): void {
            $table->dropColumn('notify');
        });
    }

    public function down(): void
    {
        Schema::table('backup_jobs', function (Blueprint $table): void {
            $table->string('notify', 16)->default('none')->after('passphrase');
        });
        foreach (DB::table('backup_jobs')->get(['id', 'notify_channels']) as $job) {
            $list = json_decode((string) $job->notify_channels, true) ?: [];
            DB::table('backup_jobs')->where('id', $job->id)->update([
                'notify' => $list[0] ?? 'none',
            ]);
        }
        Schema::table('backup_jobs', function (Blueprint $table): void {
            $table->dropColumn('notify_channels');
        });
    }
};
