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
    protected static function bootOwnsUserData(): void
    {
        static::creating(function ($model): void {
            if ($model->user_id === null && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });

        static::addGlobalScope('owner', function (Builder $query): void {
            if (Auth::check()) {
                $query->where($query->getModel()->getTable().'.user_id', Auth::id());
            }
        });
    }
}
