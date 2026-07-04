<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for the immich-machine-learning sidecar. Produces a CLIP image
 * embedding used for content-similarity duplicate detection. The /predict
 * endpoint takes a multipart request with an `entries` pipeline description and
 * an `image` file, and returns {"clip": "<json-array-string>"}.
 */
class MachineLearning
{
    public function enabled(): bool
    {
        return (bool) config('gallery.ml_enabled');
    }

    public function available(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            return Http::timeout(10)->get($this->base().'/ping')->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Embed an image file into the CLIP vector space.
     *
     * @return list<float>|null
     */
    public function embed(string $path): ?array
    {
        if (! $this->enabled() || ! is_file($path)) {
            return null;
        }

        $entries = json_encode(['clip' => ['visual' => ['modelName' => (string) config('gallery.ml_clip_model')]]], JSON_THROW_ON_ERROR);

        try {
            $res = Http::timeout(120)
                ->attach('image', (string) file_get_contents($path), basename($path))
                ->post($this->base().'/predict', ['entries' => $entries]);

            if (! $res->successful()) {
                return null;
            }

            $clip = $res->json('clip');
            // immich returns the vector as a JSON-encoded string; older builds
            // may return a real array. Handle both.
            $vector = is_string($clip) ? json_decode($clip, true) : $clip;

            return is_array($vector) && $vector !== [] ? array_map('floatval', $vector) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Detect faces in an image. Returns each face's detection score, its bounding
     * box normalised to 0..1, and its embedding vector.
     *
     * @return list<array{score: float, box: array{0: float, 1: float, 2: float, 3: float}, embedding: list<float>}>
     */
    public function detectFaces(string $path): array
    {
        if (! $this->faceEnabled() || ! is_file($path)) {
            return [];
        }

        $model = (string) config('gallery.face_model', 'buffalo_l');
        $minScore = (float) config('gallery.face_min_score', 0.7);
        $entries = json_encode([
            'facial-recognition' => [
                'recognition' => ['modelName' => $model],
                'detection' => ['modelName' => $model, 'options' => ['minScore' => $minScore]],
            ],
        ], JSON_THROW_ON_ERROR);

        try {
            $res = Http::timeout(180)
                ->attach('image', (string) file_get_contents($path), basename($path))
                ->post($this->base().'/predict', ['entries' => $entries]);

            if (! $res->successful()) {
                return [];
            }

            $w = max(1, (int) $res->json('imageWidth', 1));
            $h = max(1, (int) $res->json('imageHeight', 1));
            $faces = [];

            foreach ((array) $res->json('facial-recognition', []) as $face) {
                $box = $face['boundingBox'] ?? null;
                $embedding = $face['embedding'] ?? null;
                $embedding = is_string($embedding) ? json_decode($embedding, true) : $embedding;
                if (! is_array($box) || ! is_array($embedding) || $embedding === []) {
                    continue;
                }

                $faces[] = [
                    'score' => (float) ($face['score'] ?? 0),
                    // Normalise pixel coordinates to 0..1.
                    'box' => [
                        (float) ($box['x1'] ?? 0) / $w,
                        (float) ($box['y1'] ?? 0) / $h,
                        (float) ($box['x2'] ?? 0) / $w,
                        (float) ($box['y2'] ?? 0) / $h,
                    ],
                    'embedding' => array_map('floatval', $embedding),
                ];
            }

            return $faces;
        } catch (Throwable) {
            return [];
        }
    }

    public function faceEnabled(): bool
    {
        return (bool) config('gallery.face_enabled');
    }

    private function base(): string
    {
        return rtrim((string) config('gallery.ml_url'), '/');
    }
}
