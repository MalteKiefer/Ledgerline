<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Makes a model's rows private to their owning user. While a user is
 * authenticated (i.e. every web request, which all sit behind the `auth`
 * middleware) a global scope constrains every query to `user_id = Auth::id()`
 * and new rows get that user_id automatically.
 *
 * Outside web auth — queue jobs, scheduled commands, and the CalDAV/CardDAV
 * server (which authenticates via DavContext, not the Laravel guard) — no
 * automatic constraint is applied; those paths already scope explicitly by the
 * owning record. This keeps strict per-user isolation on the web without
 * breaking background processing.
 */
trait OwnsUserData
{
    /** The column holding the owning user id (override per model, e.g. Photo). */
    public function ownerColumn(): string
    {
        return 'user_id';
    }

    protected static function bootOwnsUserData(): void
    {
        static::creating(function ($model): void {
            $column = $model->ownerColumn();
            if ($model->{$column} === null && Auth::check()) {
                $model->{$column} = Auth::id();
            }
        });

        static::addGlobalScope('owner', function (Builder $query): void {
            if (Auth::check()) {
                $model = $query->getModel();
                $query->where($model->getTable().'.'.$model->ownerColumn(), Auth::id());
            }
        });
    }
}
