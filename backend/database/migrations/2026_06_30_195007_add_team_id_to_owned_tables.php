<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add team ownership to all owned tables and backfill existing data.
 *
 * Every owned record carries a denormalised team_id so the global team scope
 * (and route-model binding) can isolate teams directly at the query level.
 * Existing rows and users are assigned to a single "Default Team" so nothing
 * is orphaned when isolation switches on.
 */
return new class extends Migration
{
    /**
     * Tables that gain a team_id.
     *
     * @var list<string>
     */
    private array $tables = ['customers', 'contacts', 'branches', 'projects'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('team_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                $blueprint->index('team_id');
            });
        }

        // Ensure a default team exists for existing data.
        $teamId = DB::table('teams')->where('key', 'default')->value('id');

        if ($teamId === null) {
            $teamId = DB::table('teams')->insertGetId([
                'key' => 'default',
                'name' => 'Default Team',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('team_id')->update(['team_id' => $teamId]);
        }

        // Put every existing user in the default team.
        foreach (DB::table('users')->pluck('id') as $userId) {
            DB::table('team_user')->insertOrIgnore([
                'team_id' => $teamId,
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['team_id']);
                $blueprint->dropIndex(['team_id']);
                $blueprint->dropColumn('team_id');
            });
        }
    }
};
