<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\Face;
use App\Models\Person;
use App\Support\Vector;
use Illuminate\Support\Facades\DB;

/**
 * Clusters faces into people by embedding similarity. `assign()` places a single
 * face incrementally (adopt the nearest person above the threshold, else start a
 * new person); `recluster()` rebuilds every non-pinned assignment. Manually
 * pinned faces (from merge/reassign) are never moved.
 */
class FaceClusterer
{
    /** Assign one face to a person (incremental). Returns the person id. */
    public function assign(Face $face): ?string
    {
        if ($face->pinned && $face->person_id !== null) {
            return $face->person_id;
        }

        $match = $this->nearestPersonFace($face);
        $personId = $match?->person_id;

        if ($personId === null) {
            // A new cluster belongs to the same user as the face — people are
            // never shared or clustered across users.
            $personId = Person::create(['user_id' => $face->user_id])->id;
        }

        $face->forceFill(['person_id' => $personId])->save();
        $this->recompute($personId);

        return $personId;
    }

    /** Rebuild clustering across all faces, preserving manual pins. */
    public function recluster(): int
    {
        // Detach non-pinned faces and prune people that end up empty.
        Face::where('pinned', false)->update(['person_id' => null]);
        $this->pruneEmptyPeople();

        // Highest-confidence faces first so they seed the clusters.
        Face::whereNull('person_id')
            ->orderByDesc('det_score')
            ->get()
            ->each(fn (Face $face) => $this->assign($face));

        return Person::query()->count();
    }

    /** Recompute a person's face count + cover; delete the person if empty. */
    public function recompute(string $personId): void
    {
        $person = Person::find($personId);
        if ($person === null) {
            return;
        }

        $cover = Face::where('person_id', $personId)->orderByDesc('det_score')->first();
        if ($cover === null) {
            $person->delete();

            return;
        }

        $person->forceFill([
            'faces_count' => Face::where('person_id', $personId)->count(),
            'cover_face_id' => $cover->id,
        ])->save();
    }

    private function pruneEmptyPeople(): void
    {
        Person::query()->whereDoesntHave('faces')->get()->each->delete();
    }

    /**
     * The already-assigned face most similar to $face above the cluster
     * threshold, or null. Overridable in tests (pgvector isn't available on
     * sqlite). Postgres-only in production.
     */
    protected function nearestPersonFace(Face $face): ?Face
    {
        if (! Vector::available()) {
            return null;
        }

        $maxDist = 1.0 - (float) config('gallery.face_cluster_threshold', 0.5);

        $row = DB::selectOne(
            'SELECT f.id FROM faces f
             WHERE f.id <> ? AND f.person_id IS NOT NULL AND f.embedding IS NOT NULL
               AND f.user_id IS NOT DISTINCT FROM ?
               AND (f.embedding <=> (SELECT embedding FROM faces WHERE id = ?)) <= ?
             ORDER BY f.embedding <=> (SELECT embedding FROM faces WHERE id = ?) LIMIT 1',
            [$face->id, $face->user_id, $face->id, $maxDist, $face->id],
        );

        return $row !== null ? Face::find($row->id) : null;
    }
}
