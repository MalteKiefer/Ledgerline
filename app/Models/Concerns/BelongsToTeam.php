<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Customer;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the authenticated user's teams and stamps the owning team
 * on creation.
 *
 * The global scope restricts every query (including route-model binding) to the
 * current user's team ids, so a record from another team simply cannot be
 * found — the primary, query-level isolation guarantee. When there is no
 * authenticated user (console, queued jobs, tests without acting), the scope is
 * not applied so background work and seeding can run unrestricted.
 */
trait BelongsToTeam
{
    public static function bootBelongsToTeam(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void {
            $user = auth()->user();

            if ($user === null) {
                return;
            }

            $builder->whereIn(
                $builder->getModel()->getTable().'.team_id',
                $user->teamIds(),
            );
        });

        static::creating(function (Model $model): void {
            if (empty($model->team_id)) {
                $model->team_id = static::resolveOwningTeamId($model);
            }
        });
    }

    /**
     * Determine the team a new record belongs to.
     *
     * Records nested under a customer inherit that customer's team; root
     * records (customers) use the current user's active team.
     */
    protected static function resolveOwningTeamId(Model $model): ?int
    {
        if (! empty($model->customer_id)) {
            return Customer::withoutGlobalScope('team')
                ->whereKey($model->customer_id)
                ->value('team_id');
        }

        return auth()->user()?->currentTeamId();
    }

    /**
     * The owning team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
