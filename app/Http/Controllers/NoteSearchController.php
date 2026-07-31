<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only server-side search foundation for the plaintext-relational notes
 * (mobile + a future global search). The web client keeps its instant local
 * filter — this does NOT replace it.
 *
 * Owner isolation is automatic (Note uses OwnsUserData: every query is scoped
 * to the authenticated user). On PostgreSQL search uses the full-text index
 * (see the notes_fts migration); everywhere else (incl. the sqlite test driver)
 * it degrades to a portable LIKE scan. Tags are matched too.
 */
class NoteSearchController extends Controller
{
    /**
     * Full-text (or LIKE-fallback) search over the caller's notes.
     * Matches title, body and any tag. Empty query returns an empty list.
     * Newest-updated first, capped at 100 rows.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->value());
        if ($q === '') {
            return response()->json(['notes' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $pgsql = DB::getDriverName() === 'pgsql';

        $notes = Note::query()
            ->where(function ($w) use ($pgsql, $q, $like): void {
                if ($pgsql) {
                    // Full-text over the GIN index, plus a tag match (tags is a
                    // json column → cast to text for LIKE on PostgreSQL).
                    $w->whereRaw(
                        "to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(body,'')) @@ plainto_tsquery('simple', ?)",
                        [$q],
                    )->orWhereRaw('tags::text ilike ?', [$like]);
                } else {
                    // Portable fallback (sqlite/mysql): LIKE over title, body and
                    // the raw json tag text.
                    $w->where('title', 'like', $like)
                        ->orWhere('body', 'like', $like)
                        ->orWhere('tags', 'like', $like);
                }
            })
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return response()->json(['notes' => $notes]);
    }

    /**
     * Distinct tags across the caller's active notes with per-tag counts,
     * sorted by count desc then name. Read-only.
     */
    public function tags(): JsonResponse
    {
        /** @var array<string, int> $counts */
        $counts = [];
        foreach (Note::query()->get() as $note) {
            foreach ($note->tags ?? [] as $tag) {
                if (! is_string($tag) || $tag === '') {
                    continue;
                }
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        $tags = [];
        foreach ($counts as $tag => $count) {
            $tags[] = ['tag' => $tag, 'count' => $count];
        }
        usort($tags, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp((string) $a['tag'], (string) $b['tag']));

        return response()->json(['tags' => $tags]);
    }
}
