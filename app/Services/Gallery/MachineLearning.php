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

    private function base(): string
    {
        return rtrim((string) config('gallery.ml_url'), '/');
    }
}
