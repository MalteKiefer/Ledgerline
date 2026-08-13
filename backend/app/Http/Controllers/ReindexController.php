<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Files\ContentIndexer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trigger a content reindex (file text/OCR + gallery photo OCR). The work is
 * queued (it may shell out to tesseract for many files), so the request returns
 * immediately. `me` reindexes the caller's own content; `all` (admin-gated by the
 * route middleware) reindexes every user.
 */
class ReindexController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        dispatch(function () use ($uid): void {
            $indexer = new ContentIndexer;
            $indexer->reindexFiles($uid);
            $indexer->reindexGallery($uid);
        });

        return response()->json(['queued' => true]);
    }

    public function all(Request $request): JsonResponse
    {
        $this->requireUser($request);
        dispatch(function (): void {
            $indexer = new ContentIndexer;
            $indexer->reindexFiles(null);
            $indexer->reindexGallery(null);
        });

        return response()->json(['queued' => true]);
    }
}
