<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * An authenticated user, provisioned from the Pocket-ID OIDC provider.
 *
 * Users are never created with a local password; they are matched on their
 * stable OIDC subject identifier ("oidc_sub"). Membership in teams (derived
 * from Pocket-ID groups) determines which data the user can see.
 */
#[Fillable(['oidc_sub', 'name', 'email', 'avatar'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Cached team ids for the request.
     *
     * @var Collection<int, int>|null
     */
    private ?Collection $cachedTeamIds = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * The teams this user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    /**
     * The ids of the user's teams (memoised for the request).
     *
     * @return Collection<int, int>
     */
    public function teamIds(): Collection
    {
        return $this->cachedTeamIds ??= $this->teams()->pluck('teams.id');
    }

    /**
     * Whether the user is a member of the given team.
     */
    public function belongsToTeam(?int $teamId): bool
    {
        return $teamId !== null && $this->teamIds()->contains($teamId);
    }

    /**
     * The user's persisted default team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function defaultTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'default_team_id');
    }

    /**
     * Whether the user has a valid persisted default team (a team they are
     * still a member of).
     */
    public function hasChosenDefaultTeam(): bool
    {
        return $this->default_team_id !== null && $this->teamIds()->contains($this->default_team_id);
    }

    /**
     * The id of the team that new records should belong to.
     *
     * Prefers a session-selected active team, then the persisted default team,
     * then the first team the user belongs to.
     */
    public function currentTeamId(): ?int
    {
        $ids = $this->teamIds();
        $active = session('active_team_id');

        if ($active !== null && $ids->contains((int) $active)) {
            return (int) $active;
        }

        if ($this->hasChosenDefaultTeam()) {
            return $this->default_team_id;
        }

        return $ids->first();
    }

    /**
     * Forget the memoised team ids (after a membership sync).
     */
    public function forgetCachedTeamIds(): void
    {
        $this->cachedTeamIds = null;
    }
}
