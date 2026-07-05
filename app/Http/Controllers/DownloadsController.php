<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsFlexibly;
use App\Models\Export;
use App\Support\BlobStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Downloads center: lists a user's asynchronous exports and streams their
 * zip parts. Building happens on the queue (BuildExport); this controller only
 * lists, serves and removes the finished artifacts.
 */
class DownloadsController extends Controller
{
    use RespondsFlexibly;

    public function index(): View
    {
        return view('downloads.index');
    }

    /** JSON list for the reload-free page (polled while exports are building). */
    public function data(Request $request): JsonResponse
    {
        $exports = Export::query()
            ->forUser($request->user()->id)
            ->active()
            ->latest()
            ->get()
            ->map(fn (Export $e): array => [
                'id' => $e->id,
                'title' => $e->title,
                'source' => $e->source,
                'variant' => $e->variant,
                'status' => $e->status,
                'item_count' => $e->item_count,
                'part_count' => $e->part_count,
                'total_size' => $e->total_size,
                'error' => $e->error,
                'created_at' => $e->created_at?->toIso8601String(),
                'expires_at' => $e->expires_at?->toIso8601String(),
                'parts' => collect($e->parts())->values()->map(fn (array $p, int $i): array => [
                    'index' => $i,
                    'name' => $p['name'],
                    'size' => $p['size'],
                ])->all(),
            ]);

        return response()->json(['exports' => $exports]);
    }

    public function download(Request $request, Export $export, int $index): StreamedResponse
    {
        abort_unless($export->user_id === $request->user()->id, 403);
        abort_unless($export->isReady() && ! $export->isExpired(), 404);

        $parts = $export->parts();
        abort_unless(isset($parts[$index]), 404);

        $disk = BlobStore::disk();
        abort_unless($disk->exists($parts[$index]['path']), 404);

        return $disk->download($parts[$index]['path'], $parts[$index]['name']);
    }

    /** Bulk-delete selected exports (multiselect on the page) with their files. */
    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        $exports = Export::query()->forUser($request->user()->id)->whereIn('id', $ids)->get();

        foreach ($exports as $export) {
            $export->purge();
        }

        return $this->flexible($request, ['ids' => $exports->pluck('id')]);
    }
}
