<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Support\OutboundUrl;
use Illuminate\Http\Client\PendingRequest;
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
            $res = $this->client(120)
                ->attach('image', (string) file_get_contents($path), basename($path))
                ->post($this->base().'/predict', ['entries' => $entries]);

            if (! $res->successful()) {
                return null;
            }

            return $this->decodeVector($res->json('clip'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Embed a search query STRING into the same CLIP space as the image
     * embeddings, so a text query can be matched (client-side, cosine) against
     * the decrypted image vectors. No image bytes involved.
     *
     * @return list<float>|null
     */
    public function embedText(string $text): ?array
    {
        $text = trim($text);
        if (! $this->enabled() || $text === '') {
            return null;
        }

        $entries = json_encode(['clip' => ['textual' => ['modelName' => (string) config('gallery.ml_clip_model')]]], JSON_THROW_ON_ERROR);

        try {
            $res = $this->client(60)->asMultipart()->post($this->base().'/predict', [
                ['name' => 'entries', 'contents' => $entries],
                ['name' => 'text', 'contents' => $text],
            ]);

            if (! $res->successful()) {
                return null;
            }

            return $this->decodeVector($res->json('clip'));
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
            $res = $this->client(180)
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
                $embedding = $this->decodeVector($face['embedding'] ?? null);
                if (! is_array($box) || $embedding === null) {
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
                    'embedding' => $embedding,
                ];
            }

            return $faces;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Decode an immich CLIP vector from a response value: older builds return a
     * JSON-encoded string; newer builds return a real array. Returns null when
     * the value is absent or empty.
     *
     * @return list<float>|null
     */
    private function decodeVector(mixed $value): ?array
    {
        $vector = is_string($value) ? json_decode($value, true) : $value;

        return is_array($vector) && $vector !== [] ? array_map('floatval', $vector) : null;
    }

    public function faceEnabled(): bool
    {
        return (bool) config('gallery.face_enabled');
    }

    private function base(): string
    {
        return rtrim((string) config('gallery.ml_url'), '/');
    }

    /**
     * SSRF-guarded, IP-pinned client for the ML sidecar. Transiently-decrypted
     * plaintext image bytes cross this hop, so a misconfigured ML_URL must not be
     * pointable at cloud metadata / link-local / an arbitrary external host.
     */
    private function client(int $timeout): PendingRequest
    {
        return OutboundUrl::client($this->base(), $timeout);
    }
}
