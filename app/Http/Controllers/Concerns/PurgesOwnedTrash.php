<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Shared "empty trash" for the plain per-user modules (notes, to-dos,
 * bookmarks). A bulk forceDelete bypasses the model write guard, so it must be
 * owner-scoped explicitly (scopeOwnedBy) rather than relying on the auth-gated
 * global read scope — otherwise a module that later gains cross-user sharing
 * would purge rows merely shared with the caller.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
trait PurgesOwnedTrash
{
    /**
     * Permanently delete the caller's OWN trashed rows of the given model.
     *
     * @param  class-string<TModel>  $modelClass  a SoftDeletes model using AssignsOwner
     */
    protected function emptyOwnedTrash(string $modelClass): JsonResponse
    {
        // Per-model (not a bulk query delete) so model observers still fire —
        // e.g. TodoObserver::forceDeleted resyncs derived calendars. Owner-scoped
        // via scopeOwnedBy so a bulk purge can't reach merely-shared rows.
        $modelClass::ownedBy(auth()->id())->onlyTrashed()->get()->each->forceDelete();

        return response()->json(['ok' => true]);
    }
}
