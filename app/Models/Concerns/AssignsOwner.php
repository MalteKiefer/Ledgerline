<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Shared foundation for the per-user ownership traits: names the owning-user
 * column and stamps it on new rows from the authenticated user. OwnsUserData
 * and SharesWithUsers each build their own read scope on top of this; a model
 * uses exactly one of them, so this trait is pulled in once.
 */
trait AssignsOwner
{
    /** The column holding the owning user id (override per model, e.g. Photo). */
    public function ownerColumn(): string
    {
        return 'user_id';
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
