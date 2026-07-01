<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A free-text, team-scoped tag with an optional colour.
 *
 * Tags are matched on their slug within a team, so "AWS", "aws" and " AWS "
 * resolve to one row per team. Attached to projects and files.
 */
#[Fillable(['name', 'slug', 'color'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Find an existing tag by its slug within the given team (or the current
     * user's active team) or create it from the given name.
     */
    public static function findOrCreateByName(string $name, ?int $teamId = null): self
    {
        $name = trim($name);
        $teamId ??= auth()->user()?->currentTeamId();

        return static::withoutGlobalScope('team')->firstOrCreate(
            ['team_id' => $teamId, 'slug' => Str::slug($name)],
            ['name' => $name],
        );
    }

    /**
     * The projects carrying this tag.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    /**
     * The files carrying this tag.
     *
     * @return BelongsToMany<File, $this>
     */
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class);
    }
}
