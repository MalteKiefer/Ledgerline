<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner-scoped, read-only view of a mail account's sync/ingest diagnostic log.
 * Metadata only (never message content). Newest first, paginated.
 */
class MailLogController extends Controller
{
    public function index(Request $request, MailAccount $account): JsonResponse
    {
        $user = $this->requireUser($request);
        abort_if((int) $account->user_id !== (int) $user->id, 404);

        $perPage = min(200, max(10, $request->integer('per_page', 100)));

        $paginator = MailLog::query()
            ->where('account_id', $account->id)
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->string('level')->value()))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (MailLog $l): array => [
                'id' => $l->id,
                'level' => $l->level,
                'event' => $l->event,
                'folder' => $l->folder,
                'message' => $l->message,
                'created_at' => $l->created_at?->toIso8601String(),
            ])->all(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ])->header('Cache-Control', 'no-store');
    }
}
