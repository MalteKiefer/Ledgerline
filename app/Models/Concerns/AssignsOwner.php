<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Shared foundation for the per-user ownership traits: names the owning-user
 * column and stamps it on new rows from the authenticated user. OwnsUserData
 * builds its read scope on top of this; the trait is pulled in once per model.
 */
trait AssignsOwner
{
    /**
     * The column holding the owning user id. Returns 'user_id' by default;
     * override in individual models when the column name differs.
     */
    public function ownerColumn(): string
    {
        return 'user_id';
    }

    /**
     * Strictly the given user's OWN rows, with the auth-gated read scope removed
     * (never merely-shared rows). The single, model-aware way to owner-scope a
     * query — replaces hand-written withoutGlobalScopes()->where('<column>', …)
     * chains so the owner column is never hardcoded across callers.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeOwnedBy(Builder $query, int|string|null $userId): Builder
    {
        // $this is the concrete model the trait is mixed into, so getTable() and
        // ownerColumn() resolve without going through the generic Builder model.
        return $query->withoutGlobalScopes()->where($this->getTable().'.'.$this->ownerColumn(), $userId);
    }

    protected static function bootAssignsOwner(): void
    {
        static::creating(function ($model): void {
            $column = $model->ownerColumn();
            if ($model->{$column} === null && Auth::check()) {
                $model->{$column} = Auth::id();
            }
        });
    }
}
