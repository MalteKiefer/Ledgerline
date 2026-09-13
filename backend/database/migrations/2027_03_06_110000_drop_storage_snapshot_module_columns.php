<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * files_bytes and gallery_bytes on storage_snapshots have been permanently
 * pinned at 0 since the Files and Gallery modules were removed — nothing has
 * written a non-zero value to either column since. SystemStatus::snapshot()
 * and StorageHistory::capture() no longer read or write them; drop the dead
 * columns.
 *
 * This is a destructive, one-way migration — there is no down().
 */
return new class extends Migration
{
    public function up(): void
    {
        $dead = array_values(array_filter(
            ['files_bytes', 'gallery_bytes'],
            static fn (string $c): bool => Schema::hasColumn('storage_snapshots', $c),
        ));

        if ($dead !== []) {
            Schema::table('storage_snapshots', function (Blueprint $table) use ($dead): void {
                $table->dropColumn($dead);
            });
        }
    }

    public function down(): void
    {
        // Irreversible: the dropped columns were dead weight (always 0).
    }
};
