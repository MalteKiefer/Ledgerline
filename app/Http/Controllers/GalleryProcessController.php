<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Gallery\GalleryProcessor;
use App\Services\Gallery\MachineLearning;
use App\Services\Support\NominatimClient;
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
            'file' => ['required', 'file', 'max:'.((int) config('gallery.max_upload_mb', 512) * 1024)],
        ]);

        $upload = $request->file('file');
        $mime = (string) ($upload->getClientMimeType() ?: $upload->getMimeType() ?: 'application/octet-stream');

        // Move into a controlled temp path so we can guarantee the unlink; the
        // PHP upload temp is also cleaned at request end.
        $tmp = tempnam(sys_get_temp_dir(), 'gproc');
        $upload->move(dirname($tmp), basename($tmp));

        try {
            $d = $processor->process($tmp, $mime);

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
        } finally {
            // Never leave plaintext at rest — discard immediately.
            @unlink($tmp);
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
            ->map(fn ($r): array => [
                'display' => (string) ($r['display_name'] ?? ''),
                'lat' => isset($r['lat']) ? (float) $r['lat'] : null,
                'lng' => isset($r['lon']) ? (float) $r['lon'] : null,
            ])
            ->filter(fn (array $r): bool => $r['display'] !== '' && $r['lat'] !== null && $r['lng'] !== null)
            ->values()
            ->all();

        return response()->json(['results' => $results])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
