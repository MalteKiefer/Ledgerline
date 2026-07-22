<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Files\ReverseGeocoder;
use App\Services\Gallery\GalleryProcessor;
use App\Services\Gallery\MachineLearning;
use App\Services\Support\NominatimClient;
use App\Support\DiskTempFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Zero-knowledge gallery transform endpoint. The browser decrypts an original,
 * POSTs the PLAINTEXT here, we extract all derived data and return it, then the
 * plaintext is discarded — nothing is written to the DB or the object store. The
 * server only ever holds the bytes for the duration of this one request (the
 * accepted, documented transient-plaintext window). The browser encrypts the
 * returned derived data and stores it as opaque blobs.
 */
class GalleryProcessController extends Controller
{
    /** Transform one photo/video's plaintext into its derived data. */
    public function process(Request $request, GalleryProcessor $processor): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.($this->maxUploadMb() * 1024)],
        ]);

        $upload = $request->file('file');
        $mime = (string) ($upload->getClientMimeType() ?: $upload->getMimeType() ?: 'application/octet-stream');

        // Move into a controlled temp path so we can guarantee the unlink; the
        // PHP upload temp is also cleaned at request end. DiskTempFile destructs
        // at end of scope (including on throw) — no manual unlink needed.
        $tmp = DiskTempFile::create('gproc');
        $upload->move(dirname($tmp->path()), basename($tmp->path()));

        $this->guardPixelBudget($tmp->path());
        // ml=0 → "fast" upload: skip the CLIP embedding + face detection so
        // the photo is visible immediately; the client runs analyze() later.
        $d = $processor->process($tmp->path(), $mime, $request->boolean('ml', true));

        // Binary outputs are base64-encoded for the JSON envelope; the client
        // decodes, encrypts and stores each as its own opaque blob.
        return response()->json([
            'media_type' => $d['media_type'],
            'width' => $d['width'],
            'height' => $d['height'],
            'duration' => $d['duration'],
            'content_id' => $d['content_id'],
            'exif' => $d['exif'],
            'place' => $d['place'],
            'embedding' => $d['embedding'],
            // The CLIP model the embedding was produced with, so every client tags
            // `embModel` authoritatively (cross-client search coherence, spec §8.5).
            // Native clients (iOS/Go/Android) have no injected config, so they read
            // the model from here rather than assuming it.
            'model' => config('gallery.ml_clip_model'),
            'phash' => $d['phash'],
            'faces' => array_map(fn (array $f): array => [
                'score' => $f['score'],
                'box' => $f['box'],
                'embedding' => $f['embedding'],
                'crop' => $f['crop'] !== null ? base64_encode($f['crop']) : null,
            ], $d['faces']),
            'thumb' => $d['thumb'] !== null ? base64_encode($d['thumb']) : null,
            'medium' => $d['medium'] !== null ? base64_encode($d['medium']) : null,
            'motion' => $d['motion'] !== null ? base64_encode($d['motion']) : null,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Deferred vision pass. The client POSTs a photo's medium rendition
     * (plaintext, discarded after) and gets back only the CLIP embedding + faces,
     * which it merges into the photo's sealed metadata — so the heavy ML work no
     * longer blocks a photo from appearing at upload time. Same transient
     * zero-knowledge window as process().
     */
    public function analyze(Request $request, GalleryProcessor $processor): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.($this->maxUploadMb() * 1024)],
        ]);

        $upload = $request->file('file');
        $tmp = DiskTempFile::create('ganalyze');
        $upload->move(dirname($tmp->path()), basename($tmp->path()));

        $this->guardPixelBudget($tmp->path());
        $d = $processor->analyze($tmp->path());

        return response()->json([
            'embedding' => $d['embedding'],
            // The CLIP model the embedding was produced with, so every client tags
            // `embModel` identically (cross-client search coherence, spec §8.5).
            // Native clients (iOS/Go/Android) read the model authoritatively from
            // here (no injected config); the web mirrors its own config value.
            'model' => config('gallery.ml_clip_model'),
            'faces' => array_map(fn (array $f): array => [
                'score' => $f['score'],
                'box' => $f['box'],
                'embedding' => $f['embedding'],
                'crop' => $f['crop'] !== null ? base64_encode($f['crop']) : null,
            ], $d['faces']),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Fast-fail a pixel-flood / decompression bomb before ImageMagick decodes it:
     * a highly compressible image can sit well under the byte limit yet expand to
     * a huge canvas. getimagesize() reads only the header (cheap) and covers the
     * common bomb formats (JPEG/PNG/GIF/WEBP/BMP); HEIC/AVIF return nothing here
     * and fall back to the ImageMagick area policy as the backstop.
     */
    /** Max upload size in MB from config, guarded against non-numeric values. */
    private function maxUploadMb(): int
    {
        $max = config('gallery.max_upload_mb', 512);

        return is_numeric($max) ? (int) $max : 512;
    }

    private function guardPixelBudget(string $path): void
    {
        $capRaw = config('gallery.max_megapixels', 120);
        $cap = is_numeric($capRaw) ? (int) $capRaw : 120;
        if ($cap <= 0) {
            return;
        }
        $info = @getimagesize($path);
        if (is_array($info)) {
            $megapixels = ((int) $info[0] * (int) $info[1]) / 1_000_000;
            abort_if($megapixels > $cap, 422, 'Image dimensions exceed the allowed limit.');
        }
    }

    /** Embed a search query string (CLIP text space) for client-side content search. */
    public function embedText(Request $request, MachineLearning $ml): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:1024']]);

        return response()->json(['embedding' => $ml->embedText($data['q'])])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Forward-geocode a free-text place query to candidate coordinates for the
     * bulk location picker. The query and results pass through the server only
     * (client CSP forbids third-party calls) and are never persisted.
     */
    public function geocode(Request $request, NominatimClient $nominatim): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:256']]);

        $json = $nominatim->get('search', [
            'q' => $data['q'],
            'format' => 'jsonv2',
            'limit' => 6,
            'addressdetails' => 0,
        ]);

        $results = collect(is_array($json) ? $json : [])
            ->map(function ($r): array {
                $r = is_array($r) ? $r : [];
                $display = $r['display_name'] ?? '';
                $lat = $r['lat'] ?? null;
                $lon = $r['lon'] ?? null;

                return [
                    'display' => is_scalar($display) ? (string) $display : '',
                    'lat' => is_numeric($lat) ? (float) $lat : null,
                    'lng' => is_numeric($lon) ? (float) $lon : null,
                ];
            })
            ->filter(fn (array $r): bool => $r['display'] !== '' && $r['lat'] !== null && $r['lng'] !== null)
            ->values()
            ->all();

        return response()->json(['results' => $results])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Reverse-geocode a coordinate to a place name for the mobile viewer's location
     * display. Resolution stays in the ZK boundary when a self-hosted Photon is set
     * (config gallery.photon_url), falling back to the configured Nominatim otherwise;
     * the coordinate is snapped to a coarse grid inside ReverseGeocoder before egress.
     * The resolved address is returned to the caller only and NEVER cached server-side —
     * caching a plaintext location at rest would be a location leak. The mobile client
     * caches the result encrypted (sealed with the vault key) on the device.
     */
    public function reverse(Request $request, ReverseGeocoder $geocoder): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $geocoder->lookupDetailed((float) $data['lat'], (float) $data['lng']);

        return response()->json([
            'place' => $result['display'],
            'address' => $result['address'],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
