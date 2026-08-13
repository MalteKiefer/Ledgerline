<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner-scoped folder tree for the reader's left rail: the distinct
 * (account_id, folder) pairs the user has archived mail in, each with its total
 * and unread (unseen) counts. Trashed messages are excluded (Trash is a
 * separate virtual view). Omit account_id for a unified view across every
 * account.
 */
class MailFolderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        $rows = MailMessage::query()
            ->ownedBy($user->id)
            ->whereNull('trashed_at')
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->groupBy('account_id', 'folder')
            ->select('account_id', 'folder')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN seen = ? THEN 1 ELSE 0 END) AS unread', [false])
            ->orderBy('account_id')
            ->orderBy('folder')
            ->get();

        $int = static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0;

        return response()->json([
            'folders' => $rows->map(fn (MailMessage $r): array => [
                'account_id' => $r->account_id,
                'folder' => $r->folder,
                'total' => $int($r->getAttribute('total')),
                'unread' => $int($r->getAttribute('unread')),
            ])->all(),
            'total' => $rows->sum(fn (MailMessage $r): int => $int($r->getAttribute('total'))),
            'unread' => $rows->sum(fn (MailMessage $r): int => $int($r->getAttribute('unread'))),
        ]);
    }
}
