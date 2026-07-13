<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Services\Files\ReverseGeocoder;
use App\Support\ImageManagerFactory;
use Intervention\Image\Encoders\JpegEncoder;
use Throwable;

/**
 * Zero-knowledge gallery transform. Given ONE photo/video's plaintext on a local
 * (tmpfs) path, produce all derived data — EXIF, thumbnail + medium renditions,
 * motion clip (Live), CLIP embedding, detected faces (+ crops), perceptual hash,
 * reverse-geocoded place. Pure: reads the path, returns bytes/scalars, writes
 * nothing to the DB or the object store. The caller (the controller) is
 * responsible for handing the plaintext in and deleting it afterwards; the
 * browser encrypts the returned derived data and stores it as opaque blobs.
 */
class GalleryProcessor
{
    private const THUMB_WIDTH = 400;

    private const MEDIUM_WIDTH = 1600;

    public function __construct(
        private readonly ExifReader $exif,
        private readonly ImageManagerFactory $images,
        private readonly MotionPhotoExtractor $motion,
        private readonly VideoProcessor $video,
        private readonly MachineLearning $ml,
        private readonly FaceCropper $faces,
        private readonly PerceptualHash $phash,
        private readonly ReverseGeocoder $geo,
    ) {}

    /**
     * @return array{
     *   media_type: string, width: ?int, height: ?int, duration: ?float,
     *   content_id: ?string, exif: array<string,mixed>, place: array<string,mixed>,
     *   embedding: ?list<float>, phash: ?int,
     *   faces: list<array{score: float, box: array{0:float,1:float,2:float,3:float}, embedding: list<float>, crop: ?string}>,
     *   thumb: ?string, medium: ?string, motion: ?string
     * }
     */
    public function process(string $path, string $mime, bool $withMl = true): array
    {
        $isVideo = str_starts_with($mime, 'video/');

        $exif = $this->exif->read($path);

        // The image source for renditions + vision models: a video's poster
        // frame, or the image itself. tempnam() also creates the extensionless
        // base file, so both paths are tracked and discarded in the finally
        // below — no decoded plaintext still may survive any exit path.
        $posterBase = null;
        $posterTmp = null;
        $duration = null;
        $width = null;
        $height = null;

        $thumb = null;
        $medium = null;
        $motionBytes = null;
        $embedding = null;
        $faces = [];
        $phash = null;
        $place = [];

        try {
            if ($isVideo) {
                $probe = $this->video->probe($path);
                $duration = $probe['duration'] ?? null;
                $width = $probe['width'] ?? null;
                $height = $probe['height'] ?? null;
                $posterBase = tempnam(sys_get_temp_dir(), 'gposter');
                $posterTmp = $posterBase.'.jpg';
                try {
                    $this->video->poster($path, 0, $posterTmp);
                } catch (Throwable) {
                    $posterTmp = null;
                }
            }
            $imageSource = $posterTmp ?? $path;

            try {
                $manager = $this->images->make();
                $img = $manager->decodePath($imageSource);
                $width ??= $img->width();
                $height ??= $img->height();
                $thumb = (string) $img->scaleDown(width: self::THUMB_WIDTH)->encode(new JpegEncoder(quality: 75));
                $medium = (string) $manager->decodePath($imageSource)->scaleDown(width: self::MEDIUM_WIDTH)->encode(new JpegEncoder(quality: 82));
            } catch (Throwable) {
                // A non-decodable source (e.g. an unsupported codec with no poster)
                // yields no renditions; the photo still stores its original + EXIF.
            }

            // Live Photo motion clip, if this image embeds one.
            if (! $isVideo) {
                try {
                    $motionPath = $this->motion->extract($path);
                    if (is_string($motionPath) && is_file($motionPath)) {
                        $motionBytes = (string) file_get_contents($motionPath);
                        @unlink($motionPath);
                    }
                } catch (Throwable) {
                    $motionBytes = null;
                }
            }

            // Vision models (CLIP embedding + face detection) are the slow part —
            // they call the ML sidecar. On a "fast" upload we skip them so the
            // photo is visible immediately; a deferred pass runs analyze() later.
            if ($withMl) {
                [$embedding, $faces] = $this->analyzeSource($imageSource);
            }

            $phash = $this->phash->hash($imageSource);

            // Reverse-geocoding is OFF by default: with the public OSM endpoint it
            // would send the photo's coordinates off the ZK boundary on every
            // upload. Enable gallery.geocode_on_upload only with a self-hosted
            // geocoder (or when the OSM egress is acceptable); the viewer's
            // place-picker still resolves an address on demand either way.
            if (config('gallery.geocode_on_upload', false)
                && $exif['lat'] !== null && $exif['lon'] !== null) {
                try {
                    $place = $this->geo->lookupDetailed((float) $exif['lat'], (float) $exif['lon']);
                } catch (Throwable) {
                    $place = [];
                }
            }
        } finally {
            if ($posterBase !== null) {
                @unlink($posterBase);
            }
            if ($posterTmp !== null) {
                @unlink($posterTmp);
            }
        }

        return [
            'media_type' => $isVideo ? 'video' : 'image',
            'width' => $width !== null ? (int) $width : null,
            'height' => $height !== null ? (int) $height : null,
            'duration' => $duration !== null ? (float) $duration : null,
            'content_id' => $exif['content_id'] ?? null,
            'exif' => [
                'taken_at' => $exif['taken_at'] ?? null,
                'lat' => $exif['lat'] ?? null,
                'lon' => $exif['lon'] ?? null,
                'camera' => $exif['camera'] ?? null,
            ],
            'place' => $place,
            'embedding' => $embedding,
            'phash' => $phash,
            'faces' => $faces,
            'thumb' => $thumb,
            'medium' => $medium,
            'motion' => $motionBytes,
        ];
    }

    /**
     * Deferred vision pass: run the CLIP embedding + face detection on an already
     * decoded image (the client sends the medium rendition). Returns only the ML
     * outputs so the client can merge them into a photo's sealed metadata after
     * the fast upload has already made the photo visible.
     *
     * @return array{embedding: ?list<float>, faces: list<array{score: float, box: array{0:float,1:float,2:float,3:float}, embedding: list<float>, crop: ?string}>}
     */
    public function analyze(string $path): array
    {
        [$embedding, $faces] = $this->analyzeSource($path);

        return ['embedding' => $embedding, 'faces' => $faces];
    }

    /**
     * Run the vision models on one decoded image source and collect the CLIP
     * embedding plus detected faces (each with its crop). Shared by the inline
     * process() and the deferred analyze().
     *
     * @return array{0: ?list<float>, 1: list<array{score: float, box: array{0:float,1:float,2:float,3:float}, embedding: list<float>, crop: ?string}>}
     */
    private function analyzeSource(string $imageSource): array
    {
        $embedding = $this->ml->embed($imageSource);
        $faces = [];
        foreach ($this->ml->detectFaces($imageSource) as $face) {
            $crop = null;
            try {
                $crop = $this->faces->crop($imageSource, $face['box']);
            } catch (Throwable) {
                $crop = null;
            }
            $faces[] = [
                'score' => $face['score'],
                'box' => $face['box'],
                'embedding' => $face['embedding'],
                'crop' => $crop,
            ];
        }

        return [$embedding, $faces];
    }
}
