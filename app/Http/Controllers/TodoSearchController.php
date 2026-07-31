<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Todo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only server-side full-text search over the caller's own to-dos. The web
 * client keeps its instant local filter; this endpoint lets a client (mobile,
 * or the web app for large sets) search the whole store server-side.
 *
 * Owner-scoped implicitly (OwnsUserData global scope under web/api auth); the
 * search predicates are nested so the owner constraint is never widened by an
 * orWhere. PostgreSQL uses to_tsvector/plainto_tsquery (GIN-indexed), other
 * drivers a portable LIKE fallback over title/description/url + tags.
 */
class TodoSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->value());
        if ($q === '') {
            return response()->json(['todos' => []]);
        }

        $driver = (new Todo)->getConnection()->getDriverName();

        $todos = Todo::query()
            ->where(function (Builder $w) use ($q, $driver): void {
                if ($driver === 'pgsql') {
                    $w->whereRaw(
                        "to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(description,'') || ' ' || coalesce(url,'')) @@ plainto_tsquery('simple', ?)",
                        [$q],
                    )->orWhereRaw('tags::text ilike ?', ['%'.$q.'%']);
                } else {
                    $like = '%'.addcslashes($q, '%_\\').'%';
                    $w->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('url', 'like', $like)
                        ->orWhere('tags', 'like', $like);
                }
            })
            ->orderBy('done')
            ->orderByDesc('marked')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return response()->json(['todos' => $todos]);
    }
}
