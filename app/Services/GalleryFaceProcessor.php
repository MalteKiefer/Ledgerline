<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\GalleryController;
use App\Models\GalleryFace;
use App\Models\GalleryPerson;
use App\Models\GalleryPhoto;
use App\Support\DiskTempFile;
use App\Support\FaceCropper;
use App\Support\MachineLearning;
use App\Support\Vector;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Detect faces in a gallery photo, crop each, store an embedding, and greedily
 * group faces into people by embedding cosine similarity. Runs on the worker
 * (via DetectGalleryFaces). No-op when face ML / pgvector are off. Re-processing
 * a photo replaces its previous faces.
 */
class GalleryFaceProcessor
{
    public function __construct(
        private readonly MachineLearning $ml,
        private readonly FaceCropper $cropper,
        private readonly GalleryController $gallery,
    ) {}

    public function process(GalleryPhoto $photo): void
    {
        if (! $this->ml->faceEnabled() || ! Vector::available()) {
            return;
        }
        // Detect on the ML-decodable rendition (WebP preview — the sidecar's PIL
        // cannot read HEIC originals), or a video's poster frame.
        $rel = $this->gallery->mlSourcePath($photo);
        if ($rel === '' || ! $this->fs()->exists($rel)) {
            return;
        }
        $abs = $this->localPath($rel);
        $stage = null;
        if ($abs === null) {
            $stage = DiskTempFile::create('llgface');
            $in = $this->fs()->readStream($rel);
            $dst = fopen($stage->path(), 'wb');
            if (is_resource($in) && $dst !== false) {
                stream_copy_to_stream($in, $dst);
                fclose($in);
                fclose($dst);
                $abs = $stage->path();
            }
        }
        if ($abs === null) {
            return;
        }

        $faces = $this->ml->detectFaces($abs);

        // Drop the photo's previous faces + their crops (idempotent reprocess).
        foreach (GalleryFace::query()->where('gallery_photo_id', $photo->id)->get() as $old) {
            $this->deleteCrop($old->crop_path);
            $old->delete();
        }
        $this->reapCoverlessPeople((int) $photo->user_id);

        $uid = (int) $photo->user_id;
        foreach ($faces as $face) {
            $embedding = $face['embedding'];
            $cropRel = null;
            $bytes = $this->cropper->crop($abs, $face['box']);
            if ($bytes !== null) {
                $cropRel = 'gallery/faces/'.Str::uuid()->toString().'.jpg';
                $this->fs()->put($cropRel, $bytes);
            }

            $personId = $this->matchPerson($uid, $embedding);
            $isNewPerson = false;
            if ($personId === null) {
                $person = new GalleryPerson;
                $person->forceFill(['user_id' => $uid]);
                $person->save();
                $personId = (int) $person->id;
                $isNewPerson = true;
            }

            $model = new GalleryFace;
            $model->forceFill([
                'user_id' => $uid,
                'gallery_photo_id' => $photo->id,
                'gallery_person_id' => $personId,
                'box' => array_values($face['box']),
                'score' => $face['score'],
                'crop_path' => $cropRel,
            ]);
            $model->save();

            DB::update('UPDATE gallery_faces SET embedding = ?::vector WHERE id = ?', [
                MachineLearning::toVectorLiteral($embedding), $model->id,
            ]);

            if ($isNewPerson) {
                GalleryPerson::query()->whereKey($personId)->update(['cover_face_id' => $model->id]);
            }
        }
    }

    /**
     * Nearest existing person's face within the match threshold, or null.
     *
     * @param  list<float>  $embedding
     */
    private function matchPerson(int $uid, array $embedding): ?int
    {
        $maxCfg = config('ml.face_match_distance', 0.35);
        $max = is_numeric($maxCfg) ? (float) $maxCfg : 0.35;
        $row = DB::selectOne(
            'SELECT gallery_person_id FROM gallery_faces
             WHERE user_id = ? AND gallery_person_id IS NOT NULL AND embedding IS NOT NULL AND hidden = false
               AND (embedding <=> ?::vector) < ?
             ORDER BY embedding <=> ?::vector LIMIT 1',
            [$uid, MachineLearning::toVectorLiteral($embedding), $max, MachineLearning::toVectorLiteral($embedding)],
        );

        return is_object($row) && isset($row->gallery_person_id) && is_numeric($row->gallery_person_id)
            ? (int) $row->gallery_person_id
            : null;
    }

    /** Remove auto-created (unnamed) people that no longer have any faces. */
    private function reapCoverlessPeople(int $uid): void
    {
        GalleryPerson::query()
            ->where('user_id', $uid)
            ->whereNull('name')
            ->whereDoesntHave('faces')
            ->delete();
    }

    /**
     * Re-generate a single face's crop from its source photo using the stored
     * box — non-destructive (keeps the person link/name). Returns true if a crop
     * was written. Used by `gallery:recrop` to restore lost crop files.
     */
    public function recropFace(GalleryFace $face): bool
    {
        $photo = $face->photo;
        if (! $photo instanceof GalleryPhoto) {
            return false;
        }
        $rel = $this->gallery->mlSourcePath($photo);
        if ($rel === '' || ! $this->fs()->exists($rel)) {
            return false;
        }
        $abs = $this->localPath($rel);
        $stage = null;
        if ($abs === null) {
            $stage = DiskTempFile::create('llgrecrop');
            $in = $this->fs()->readStream($rel);
            $dst = fopen($stage->path(), 'wb');
            if (is_resource($in) && $dst !== false) {
                stream_copy_to_stream($in, $dst);
                fclose($in);
                fclose($dst);
                $abs = $stage->path();
            }
        }
        if ($abs === null) {
            return false;
        }
        $box = is_array($face->box) ? array_values($face->box) : [];
        if (count($box) < 4) {
            return false;
        }
        /** @var array{0:float,1:float,2:float,3:float} $box */
        $bytes = $this->cropper->crop($abs, $box);
        unset($stage); // RAII temp file cleaned up here
        if ($bytes === null) {
            return false;
        }
        $this->deleteCrop($face->crop_path);
        $cropRel = 'gallery/faces/'.Str::uuid()->toString().'.jpg';
        $this->fs()->put($cropRel, $bytes);
        $face->forceFill(['crop_path' => $cropRel])->save();

        return true;
    }

    private function deleteCrop(?string $path): void
    {
        if (is_string($path) && $path !== '' && str_starts_with($path, 'gallery/faces/') && ! str_contains($path, '..')) {
            $this->fs()->delete($path);
        }
    }

    private function fs(): Filesystem
    {
        $d = config('files.disk');

        return Storage::disk(is_string($d) ? $d : 'files');
    }

    private function localPath(string $rel): ?string
    {
        $disk = $this->fs();

        return $disk instanceof FilesystemAdapter ? $disk->path($rel) : null;
    }
}
