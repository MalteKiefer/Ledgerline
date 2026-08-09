<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner-scoped storage stats for the mail archive: message count + byte total
 * overall and grouped per account and per folder. (Content-level de-duplication
 * is already enforced at ingest by the unique(user_id, content_hash) index, so
 * there are never stored duplicates to report.)
 */
class MailStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $int = static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0;

        $perAccount = MailMessage::query()->ownedBy($uid)
            ->groupBy('account_id')
            ->selectRaw('account_id, COUNT(*) AS count, SUM(size) AS bytes')
            ->get()
            ->map(fn (MailMessage $r): array => [
                'account_id' => $r->account_id,
                'count' => $int($r->getAttribute('count')),
                'bytes' => $int($r->getAttribute('bytes')),
            ])->all();

        $perFolder = MailMessage::query()->ownedBy($uid)
            ->groupBy('account_id', 'folder')
            ->selectRaw('account_id, folder, COUNT(*) AS count, SUM(size) AS bytes')
            ->get()
            ->map(fn (MailMessage $r): array => [
                'account_id' => $r->account_id,
                'folder' => $r->folder,
                'count' => $int($r->getAttribute('count')),
                'bytes' => $int($r->getAttribute('bytes')),
            ])->all();

        return response()->json([
            'total_messages' => MailMessage::query()->ownedBy($uid)->count(),
            'total_bytes' => $int(MailMessage::query()->ownedBy($uid)->sum('size')),
            'per_account' => $perAccount,
            'per_folder' => $perFolder,
        ]);
    }
}
