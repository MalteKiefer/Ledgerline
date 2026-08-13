<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user isolation, phase 3: gallery. Photos already carry uploaded_by (the
 * owner); people/faces gain user_id. Existing rows backfill to the first user
 * so face/duplicate clustering happens within one owner, never across users.
 */
return new class extends Migration
{
    public function up(): void
    {
        $firstUserId = User::query()->orderBy('id')->value('id');

        // Photos: owner is the existing uploaded_by; backfill any nulls.
        if ($firstUserId !== null) {
            DB::table('photos')->whereNull('uploaded_by')->update(['uploaded_by' => $firstUserId]);
        }

        foreach (['people', 'faces'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->foreignId('user_id')->nullable()->index();
            });
            if ($firstUserId !== null) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $firstUserId]);
            }
        }
    }

    public function down(): void
    {
        foreach (['people', 'faces'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('user_id'));
        }
    }
};
