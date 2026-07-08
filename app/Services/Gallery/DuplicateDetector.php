<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\Photo;
use App\Support\BlobStore;
use App\Support\DiskTempFile;
use App\Support\Vector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clusters photos that depict the same/similar content into duplicate groups,
 * using a cheap perceptual-hash pre-pass (near-identical) and, on Postgres with
 * pgvector, CLIP-embedding cosine similarity. Members of a cluster share a
 * duplicate_group_id; dismissed photos are excluded. Idempotent.
 */
class DuplicateDetector
{
    /** @var array<int|string, int|string> union-find parent map */
    private array $parent = [];

    /** @var array<int|string, float> best similarity found per photo */
    private array $score = [];

    public function __construct(private readonly PerceptualHash $hasher) {}

    /** Run detection over the whole library. Returns the number of groups formed. */
    public function run(): int
    {
        $this->parent = [];
        $this->score = [];

        // Clean slate for all live, non-dismissed photos.
        Photo::query()->whereNull('dup_dismissed_at')
            ->update(['duplicate_group_id' => null, 'dup_score' => null]);

        // Make sure every ready photo has a perceptual hash before comparing.
        // Videos in particular are hashed from their poster frame (medium_path);
        // without this, any photo/video that never got a hash at upload — or
        // whose async backfill has not run yet — is silently skipped and never
        // flagged as a duplicate.
        $this->backfillHashes();

        $this->perceptualPass();
        if (Vector::available()) {
            $this->embeddingPass();
        }

        return $this->assignGroups();
    }

    /**
     * Compute and store a perceptual hash for every ready photo still missing
     * one, reading the medium rendition (for videos, the poster frame). Mirrors
     * EmbedPhoto's hashing so detection never depends on that async job having
     * finished first.
     */
    private function backfillHashes(): void
    {
        $disk = BlobStore::disk();

        Photo::query()
            ->where('status', 'ready')
            ->whereNull('phash')
            ->whereNull('dup_dismissed_at')
            ->eachById(function (Photo $photo) use ($disk): void {
                $path = $photo->medium_path ?: $photo->disk_path;
                if ($path === null || ! $disk->exists($path)) {
                    return;
                }
                $tmp = DiskTempFile::pull($disk, $path, 'dup');
                try {
                    $hash = $this->hasher->hash($tmp);
                    if ($hash !== null) {
                        $photo->forceFill(['phash' => $hash])->save();
                    }
                } finally {
                    @unlink($tmp);
                }
            });
    }

    private function perceptualPass(): void
    {
        $threshold = (int) config('gallery.phash_max_distance', 6);

        $rows = Photo::query()
            ->whereNull('dup_dismissed_at')
            ->whereNotNull('phash')
            ->get(['id', 'phash', 'media_type', 'uploaded_by']);

        // Compare within each media type AND owner only — never dedupe one
        // user's photos against another's.
        foreach ($rows->groupBy(fn (Photo $p): string => $p->media_type.'|'.$p->uploaded_by) as $group) {
            $list = $group->values();
            $n = $list->count();
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $dist = $this->hasher->hamming((int) $list[$i]->phash, (int) $list[$j]->phash);
                    if ($dist <= $threshold) {
                        $sim = 1.0 - $dist / 64.0;
                        $this->link($list[$i]->id, $list[$j]->id, $sim);
                    }
                }
            }
        }
    }

    private function embeddingPass(): void
    {
        $maxDist = 1.0 - (float) config('gallery.duplicate_threshold', 0.92);

        $candidates = DB::select("SELECT id, media_type, uploaded_by, embedding::text AS vec FROM photos
            WHERE embedding IS NOT NULL AND dup_dismissed_at IS NULL AND deleted_at IS NULL AND status = 'ready'");

        foreach ($candidates as $c) {
            // Nearest neighbours via the HNSW index (literal vector param),
            // restricted to the same owner.
            $neighbours = DB::select(
                "SELECT id, (embedding <=> ?::vector) AS dist FROM photos
                 WHERE id <> ? AND embedding IS NOT NULL AND media_type = ?
                   AND uploaded_by IS NOT DISTINCT FROM ?
                   AND dup_dismissed_at IS NULL AND deleted_at IS NULL AND status = 'ready'
                 ORDER BY embedding <=> ?::vector LIMIT 25",
                [$c->vec, $c->id, $c->media_type, $c->uploaded_by, $c->vec],
            );

            foreach ($neighbours as $n) {
                if ((float) $n->dist <= $maxDist) {
                    $this->link($c->id, $n->id, 1.0 - (float) $n->dist);
                }
            }
        }
    }

    private function assignGroups(): int
    {
        // Collect union members.
        $members = [];
        foreach (array_keys($this->parent) as $id) {
            $members[$this->find($id)][] = $id;
        }

        $groups = 0;
        DB::transaction(function () use ($members, &$groups): void {
            foreach ($members as $cluster) {
                if (count($cluster) < 2) {
                    continue;
                }
                $groupId = (string) Str::uuid();
                $groups++;
                foreach ($cluster as $id) {
                    Photo::query()->whereKey($id)->update([
                        'duplicate_group_id' => $groupId,
                        'dup_score' => $this->score[$id] ?? null,
                    ]);
                }
            }
        });

        return $groups;
    }

    private function link(int|string $a, int|string $b, float $sim): void
    {
        $this->union($a, $b);
        $this->score[$a] = max($this->score[$a] ?? 0.0, $sim);
        $this->score[$b] = max($this->score[$b] ?? 0.0, $sim);
    }

    private function find(int|string $x): int|string
    {
        $this->parent[$x] ??= $x;
        while ($this->parent[$x] !== $x) {
            $this->parent[$x] = $this->parent[$this->parent[$x]] ?? $this->parent[$x];
            $x = $this->parent[$x];
        }

        return $x;
    }

    private function union(int|string $a, int|string $b): void
    {
        $ra = $this->find($a);
        $rb = $this->find($b);
        if ($ra !== $rb) {
            $this->parent[$ra] = $rb;
        }
    }
}
