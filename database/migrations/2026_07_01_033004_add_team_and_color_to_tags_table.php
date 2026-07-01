<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make tags team-scoped and give them a colour.
 *
 * Tags are deduplicated per team (unique team_id + slug) instead of globally,
 * so each team manages its own tags. Existing tags are assigned to the team of
 * a record they are attached to (falling back to the default team).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('color', 7)->nullable()->after('slug');
        });

        // Backfill team_id from an attached project or file.
        $default = DB::table('teams')->where('key', 'default')->value('id');

        foreach (DB::table('tags')->whereNull('team_id')->pluck('id') as $tagId) {
            $teamId = DB::table('project_tag')
                ->join('projects', 'projects.id', '=', 'project_tag.project_id')
                ->where('project_tag.tag_id', $tagId)
                ->value('projects.team_id')
                ?? DB::table('file_tag')
                    ->join('files', 'files.id', '=', 'file_tag.file_id')
                    ->where('file_tag.tag_id', $tagId)
                    ->value('files.team_id')
                ?? $default;

            if ($teamId !== null) {
                DB::table('tags')->where('id', $tagId)->update(['team_id' => $teamId]);
            }
        }

        Schema::table('tags', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->unique(['team_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'slug']);
            $table->dropForeign(['team_id']);
            $table->dropColumn(['team_id', 'color']);
            $table->unique('slug');
        });
    }
};
