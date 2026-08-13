<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Server-side content search over files. Matches the extracted `search_text`
 * (see FileTextIndex) plus the file name. On PostgreSQL it uses the GIN
 * full-text index (to_tsvector('simple') @@ plainto_tsquery); on SQLite it
 * falls back to LIKE. Rows are owner-scoped by the FileEntry model's global
 * scope (OwnsUserData), so a user only ever searches their own files.
 *
 * Returns the same file-row shape FilesController@index emits (the serialized
 * FileEntry model) under a `files` key, so the client can reuse its row render.
 */
class FileSearchController extends Controller
{
    private const LIMIT = 100;

    public function search(Request $request): JsonResponse
    {
        // Auth is required by the route middleware; this fails closed otherwise.
        $this->requireUser($request);

        $q = trim($request->string('q')->value());
        if ($q === '') {
            return response()->json(['files' => []]);
        }

        $like = '%'.$q.'%';
        $query = FileEntry::query();

        if (DB::getDriverName() === 'pgsql') {
            // Full-text over the GIN index, with a name LIKE fallback (catches
            // filename hits and prefixes the tsquery would miss).
            $query->where(function (Builder $inner) use ($q, $like): void {
                $inner->whereRaw(
                    "to_tsvector('simple', coalesce(search_text, '')) @@ plainto_tsquery('simple', ?)",
                    [$q]
                )->orWhere('name', 'like', $like);
            });
        } else {
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('search_text', 'like', $like);
            });
        }

        $files = $query->orderByDesc('updated_at')->limit(self::LIMIT)->get();

        return response()->json(['files' => $files]);
    }
}
