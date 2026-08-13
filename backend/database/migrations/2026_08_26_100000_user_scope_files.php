<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user isolation, phase 2: files and folders. Adds an indexed user_id and
 * backfills existing (shared) rows to the first user.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['file_folders', 'files'];

    public function up(): void
    {
        $firstUserId = User::query()->orderBy('id')->value('id');

        foreach ($this->tables as $table) {
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
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('user_id'));
        }
    }
};
