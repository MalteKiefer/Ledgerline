<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for the immich-machine-learning sidecar (CLIP embeddings). The image
 * and a search query embed into the SAME vector space, so a text query ("Baum")
 * matches photos of trees via cosine similarity in pgvector.
 *
 * The /predict endpoint takes a multipart request with an `entries` pipeline
 * description + an `image` file (or a `text` field) and returns
 * {"clip": "<json-array-string>"}. The sidecar URL is fixed internal config
 * (never user input). Every call is best-effort: any failure returns null and
 * the feature degrades (no embedding / empty search).
 */
class MachineLearning
{
    public function enabled(): bool
    {
        return (bool) config('ml.enabled');
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
        try {
            $entries = json_encode(['clip' => ['visual' => ['modelName' => $this->model()]]], JSON_THROW_ON_ERROR);
            $res = Http::timeout(120)
                ->attach('image', (string) file_get_contents($path), basename($path))
                ->post($this->base().'/predict', ['entries' => $entries]);

            return $res->successful() ? $this->decodeVector($res->json('clip')) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Embed a search query string into the same CLIP space as the images.
     *
     * @return list<float>|null
     */
    public function embedText(string $text): ?array
    {
        $text = trim($text);
        if (! $this->enabled() || $text === '') {
            return null;
        }
        try {
            $entries = json_encode(['clip' => ['textual' => ['modelName' => $this->model()]]], JSON_THROW_ON_ERROR);
            $res = Http::timeout(60)->asMultipart()->post($this->base().'/predict', [
                ['name' => 'entries', 'contents' => $entries],
                ['name' => 'text', 'contents' => $text],
            ]);

            return $res->successful() ? $this->decodeVector($res->json('clip')) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function faceEnabled(): bool
    {
        return (bool) config('ml.face_enabled');
    }

    /**
     * Detect faces in an image. Each face: detection score, bounding box
     * normalised to 0..1, and a 512-dim face embedding.
     *
     * @return list<array{score: float, box: array{0: float, 1: float, 2: float, 3: float}, embedding: list<float>}>
     */
    public function detectFaces(string $path): array
    {
        if (! $this->faceEnabled() || ! is_file($path)) {
            return [];
        }
        $modelCfg = config('ml.face_model');
        $model = is_string($modelCfg) && $modelCfg !== '' ? $modelCfg : 'buffalo_l';
        $minCfg = config('ml.face_min_score');
        $minScore = is_numeric($minCfg) ? (float) $minCfg : 0.7;
        try {
            $entries = json_encode([
                'facial-recognition' => [
                    'recognition' => ['modelName' => $model],
                    'detection' => ['modelName' => $model, 'options' => ['minScore' => $minScore]],
                ],
            ], JSON_THROW_ON_ERROR);
            $res = Http::timeout(180)
                ->attach('image', (string) file_get_contents($path), basename($path))
                ->post($this->base().'/predict', ['entries' => $entries]);
            if (! $res->successful()) {
                return [];
            }
            $wRaw = $res->json('imageWidth', 1);
            $hRaw = $res->json('imageHeight', 1);
            $w = max(1, is_numeric($wRaw) ? (int) $wRaw : 1);
            $h = max(1, is_numeric($hRaw) ? (int) $hRaw : 1);

            $faces = [];
            foreach ((array) $res->json('facial-recognition', []) as $face) {
                if (! is_array($face)) {
                    continue;
                }
                $box = $face['boundingBox'] ?? null;
                $embedding = $this->decodeVector($face['embedding'] ?? null);
                if (! is_array($box) || $embedding === null || count($embedding) !== 512) {
                    continue;
                }
                $faces[] = [
                    'score' => is_numeric($face['score'] ?? null) ? (float) $face['score'] : 0.0,
                    'box' => [
                        max(0.0, min(1.0, (is_numeric($box['x1'] ?? null) ? (float) $box['x1'] : 0.0) / $w)),
                        max(0.0, min(1.0, (is_numeric($box['y1'] ?? null) ? (float) $box['y1'] : 0.0) / $h)),
                        max(0.0, min(1.0, (is_numeric($box['x2'] ?? null) ? (float) $box['x2'] : 0.0) / $w)),
                        max(0.0, min(1.0, (is_numeric($box['y2'] ?? null) ? (float) $box['y2'] : 0.0) / $h)),
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
     * Format a float vector as a pgvector literal: [a,b,c].
     *
     * @param  list<float>  $vec
     */
    public static function toVectorLiteral(array $vec): string
    {
        return '['.implode(',', array_map(static fn (float $f): string => (string) $f, $vec)).']';
    }

    /**
     * The sidecar returns the CLIP vector either as a JSON-array string or an
     * already-decoded array. Normalise to a plain float list.
     *
     * @return list<float>|null
     */
    private function decodeVector(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($raw) || $raw === []) {
            return null;
        }
        $out = [];
        foreach ($raw as $v) {
            if (! is_numeric($v)) {
                return null;
            }
            $out[] = (float) $v;
        }

        return $out;
    }

    private function base(): string
    {
        $url = config('ml.url');
        $default = 'http://ml:3003';
        // Fail-safe egress guard: even though ml.url is admin-only, never ship
        // decrypted photo bytes to a link-local / cloud-metadata target — fall
        // back to the internal sidecar if the configured host is not safe.
        if (! is_string($url) || $url === '' || ! OutboundUrl::safe($url)) {
            return $default;
        }

        return rtrim($url, '/');
    }

    private function model(): string
    {
        $model = config('ml.clip_model');

        return is_string($model) ? $model : 'ViT-B-32__openai';
    }
}
