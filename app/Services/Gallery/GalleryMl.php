<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\GalleryFace;
use App\Models\GalleryPerson;
use App\Models\GalleryPhoto;
use App\Support\DiskTempFile;
use App\Support\Vector;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Machine-learning glue for the plaintext-relational Gallery: persists the CLIP
 * image embedding + detected faces a photo yields, auto-groups each new face to
 * the nearest known person (pgvector cosine, Postgres only), and answers the
 * semantic-search / similar-photo vector queries. Kept out of the controller so
 * the controller stays a thin request/response layer.
 *
 * All pgvector access is guarded by Vector::available(): on sqlite (tests) or a
 * plain Postgres the embedding columns don't exist, so embeddings are simply not
 * written, faces are never grouped (each becomes its own person), and search
 * returns nothing — the relational plumbing (rows, crops, people) still works.
 */
class GalleryMl
{
    /** Max photos returned by a semantic / similar query. */
    private const SEARCH_LIMIT = 60;

    public function __construct(
        private readonly MachineLearning $ml,
        private readonly GalleryProcessor $processor,
    ) {}

    public function enabled(): bool
    {
        return $this->ml->enabled();
    }

    private function disk(): string
    {
        $d = config('files.disk');

        return is_string($d) ? $d : 'files';
    }

    private function fs(): Filesystem
    {
        return Storage::disk($this->disk());
    }

    /**
     * Persist the ML outputs of a freshly-uploaded photo (the embedding + faces
     * the processor already produced). Runs only when ML is enabled; each face
     * gets a crop on disk + a row + a person assignment. Best-effort — the caller
     * wraps this in try/catch so an ML/DB hiccup never fails the upload itself.
     *
     * @param  array{embedding: ?list<float>, faces: list<array{score: float, box: array{0:float,1:float,2:float,3:float}, embedding: list<float>, crop: ?string}>}  $derived
     */
    public function storeDerived(GalleryPhoto $photo, array $derived): void
    {
        if (! $this->enabled()) {
            return;
        }

        DB::transaction(function () use ($photo, $derived): void {
            $this->applyEmbedding($photo, $derived['embedding']);
            foreach ($derived['faces'] as $face) {
                $this->storeFace($photo, $face);
            }
        });
    }

    /**
     * Re-run the vision models on one already-stored photo and replace its
     * embedding + faces. Used by the reprocess endpoint (a photo uploaded while
     * ML was off) and the gallery:backfill-ml command. Decodes the medium
     * rendition when present (smaller + always a still), else the original.
     */
    public function reprocess(GalleryPhoto $photo): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $source = is_string($photo->medium_path) && $photo->medium_path !== '' ? $photo->medium_path : $photo->storage_path;
        if ($source === '' || ! $this->fs()->exists($source)) {
            return false;
        }

        $ext = $source === $photo->medium_path ? 'webp' : 'bin';
        $tmp = DiskTempFile::create('glreproc')->withExtension($ext);
        $bytes = $this->fs()->get($source);
        if (! is_string($bytes)) {
            return false;
        }
        file_put_contents($tmp->path(), $bytes);

        $result = $this->processor->analyze($tmp->path());

        DB::transaction(function () use ($photo, $result): void {
            foreach (GalleryFace::query()->withoutGlobalScopes()->where('gallery_photo_id', $photo->id)->get() as $old) {
                if (is_string($old->crop_path) && $old->crop_path !== '') {
                    $this->fs()->delete($old->crop_path);
                }
                $old->delete();
            }
            $this->applyEmbedding($photo, $result['embedding']);
            foreach ($result['faces'] as $face) {
                $this->storeFace($photo, $face);
            }
        });

        return true;
    }

    /**
     * Semantic text search: CLIP-embed the query and order the user's photos by
     * cosine distance. Empty (graceful) when ML is off, pgvector is unavailable,
     * or the query can't be embedded.
     *
     * @return array<int, float> photo id => cosine distance, nearest first
     */
    public function searchText(int $uid, string $q): array
    {
        if (! $this->enabled() || ! Vector::available()) {
            return [];
        }
        $vector = $this->ml->embedText($q);
        if ($vector === null) {
            return [];
        }

        $rows = DB::select(
            'SELECT id, (embedding <=> ?::vector) AS distance
             FROM gallery_photos
             WHERE user_id = ? AND deleted_at IS NULL AND embedding IS NOT NULL
             ORDER BY distance ASC LIMIT ?',
            [$this->vec($vector), $uid, self::SEARCH_LIMIT],
        );

        return $this->distanceMap($rows);
    }

    /**
     * Image→image similarity: photos nearest to the given photo's own embedding.
     *
     * @return array<int, float> photo id => cosine distance, nearest first
     */
    public function similarTo(int $uid, GalleryPhoto $photo): array
    {
        if (! Vector::available()) {
            return [];
        }
        $self = DB::selectOne(
            'SELECT embedding FROM gallery_photos WHERE id = ? AND user_id = ? AND embedding IS NOT NULL',
            [$photo->id, $uid],
        );
        $selfEmbedding = is_object($self) ? (get_object_vars($self)['embedding'] ?? null) : null;
        if (! is_string($selfEmbedding) || $selfEmbedding === '') {
            return [];
        }

        $rows = DB::select(
            'SELECT id, (embedding <=> ?::vector) AS distance
             FROM gallery_photos
             WHERE user_id = ? AND deleted_at IS NULL AND embedding IS NOT NULL AND id <> ?
             ORDER BY distance ASC LIMIT ?',
            [$selfEmbedding, $uid, $photo->id, self::SEARCH_LIMIT],
        );

        return $this->distanceMap($rows);
    }

    // ---- internals ----

    /**
     * Write the photo's embedding (pgvector) + stamp embedded_at (always).
     *
     * @param  ?list<float>  $embedding
     */
    private function applyEmbedding(GalleryPhoto $photo, ?array $embedding): void
    {
        $now = now();
        if ($embedding !== null && Vector::available()) {
            Vector::store('gallery_photos', $photo->id, $embedding, ['embedded_at' => $now->toDateTimeString()]);
        } else {
            GalleryPhoto::query()->withoutGlobalScopes()->where('id', $photo->id)->update(['embedded_at' => $now]);
        }
        $photo->embedded_at = $now;
    }

    /**
     * Store one detected face: its crop on disk, its row, its person assignment,
     * and (Postgres) its embedding. Sets a freshly-created person's cover.
     *
     * @param  array{score: float, box: array{0:float,1:float,2:float,3:float}, embedding: list<float>, crop: ?string}  $face
     */
    private function storeFace(GalleryPhoto $photo, array $face): void
    {
        $uid = (int) $photo->user_id;

        $cropPath = null;
        $crop = $face['crop'];
        if (is_string($crop) && $crop !== '') {
            $cropPath = 'gallery/faces/'.Str::uuid()->toString().'.jpg';
            $this->fs()->put($cropPath, $crop);
        }

        $personId = $this->assignPerson($uid, $face['embedding']);

        $row = new GalleryFace;
        $row->forceFill([
            'user_id' => $uid,
            'gallery_photo_id' => $photo->id,
            'gallery_person_id' => $personId,
            'score' => $face['score'],
            'box' => $face['box'],
            'crop_path' => $cropPath,
            'hidden' => false,
        ]);
        $row->save();

        if (Vector::available()) {
            DB::update('UPDATE gallery_faces SET embedding = ?::vector WHERE id = ?', [$this->vec($face['embedding']), $row->id]);
        }

        // Give an unnamed cluster a cover so the people list can show a face.
        GalleryPerson::query()->withoutGlobalScopes()
            ->where('id', $personId)->whereNull('cover_face_id')
            ->update(['cover_face_id' => $row->id]);
    }

    /**
     * Resolve the person a new face belongs to: the nearest existing face within
     * the cosine threshold (Postgres), else a brand-new unnamed person.
     *
     * @param  list<float>  $embedding
     */
    private function assignPerson(int $uid, array $embedding): int
    {
        if ($embedding !== [] && Vector::available()) {
            $thrCfg = config('gallery.face_match_distance', 0.35);
            $threshold = is_numeric($thrCfg) ? (float) $thrCfg : 0.35;

            $row = DB::selectOne(
                'SELECT gallery_person_id AS pid, (embedding <=> ?::vector) AS distance
                 FROM gallery_faces
                 WHERE user_id = ? AND gallery_person_id IS NOT NULL AND embedding IS NOT NULL
                 ORDER BY distance ASC LIMIT 1',
                [$this->vec($embedding), $uid],
            );
            $vars = is_object($row) ? get_object_vars($row) : [];
            $distance = $vars['distance'] ?? null;
            $pid = $vars['pid'] ?? null;
            if (is_numeric($distance) && (float) $distance <= $threshold && is_numeric($pid)) {
                return (int) $pid;
            }
        }

        $person = new GalleryPerson;
        $person->forceFill(['user_id' => $uid]);
        $person->save();

        return (int) $person->id;
    }

    /**
     * Turn raw DB rows ({id, distance}) into an ordered id => distance map.
     *
     * @param  array<array-key, mixed>  $rows
     * @return array<int, float>
     */
    private function distanceMap(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $vars = is_object($r) ? get_object_vars($r) : (is_array($r) ? $r : []);
            $id = $vars['id'] ?? null;
            if (is_numeric($id)) {
                $distance = $vars['distance'] ?? null;
                $out[(int) $id] = is_numeric($distance) ? (float) $distance : 0.0;
            }
        }

        return $out;
    }

    /**
     * pgvector text literal for a float list: [v1,v2,...].
     *
     * @param  list<float>  $vector
     */
    private function vec(array $vector): string
    {
        return '['.implode(',', array_map(static fn (float $v): string => (string) $v, $vector)).']';
    }
}
