<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the team feature entirely.
 *
 * Team-based isolation is dropped: every owned record loses its team_id and all
 * data becomes a single shared workspace. Tags revert to a global unique slug
 * (duplicates created per team are merged first). The teams and team_user
 * tables and the users.default_team_id column are removed.
 */
return new class extends Migration
{
    /** Owned tables that carry a team_id foreign key. */
    private array $tables = ['customers', 'contacts', 'branches', 'projects', 'files'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            // Drop the team_id index first (SQLite cannot drop an indexed column).
            try {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($table.'_team_id_index'));
            } catch (Throwable) {
                // No such index on this table; nothing to drop.
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('team_id');
            });
        }

        $this->mergeDuplicateTagSlugs();

        Schema::table('tags', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'slug']);
            $table->dropConstrainedForeignId('team_id');
            $table->unique('slug');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_team_id');
        });

        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }

    /**
     * Merge tags that share a slug (possible once slugs are global again),
     * repointing pivot rows to the lowest-id tag and deleting the duplicates.
     */
    private function mergeDuplicateTagSlugs(): void
    {
        $slugs = DB::table('tags')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');

        foreach ($slugs as $slug) {
            $ids = DB::table('tags')->where('slug', $slug)->orderBy('id')->pluck('id');
            $keep = $ids->first();

            foreach ($ids->skip(1) as $duplicate) {
                foreach ([['project_tag', 'project_id'], ['file_tag', 'file_id']] as [$pivot, $column]) {
                    DB::table($pivot)
                        ->where('tag_id', $duplicate)
                        ->whereIn($column, fn ($q) => $q->select($column)->from($pivot)->where('tag_id', $keep))
                        ->delete();

                    DB::table($pivot)->where('tag_id', $duplicate)->update(['tag_id' => $keep]);
                }

                DB::table('tags')->where('id', $duplicate)->delete();
            }
        }
    }

    public function down(): void
    {
        // One-way migration: the team feature is not restored.
    }
};
