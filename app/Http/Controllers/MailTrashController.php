<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Soft archive/hide for archived mail. Archived messages are IMMUTABLE and are
 * NEVER hard-deleted: "delete" only stamps trashed_at (hides the message from
 * the default list); "restore" clears it. There is deliberately no endpoint
 * that removes a message row or its sealed blob — the only true deletion is a
 * full GDPR user-account purge. Both actions are bulk + owner-scoped.
 */
class MailTrashController extends Controller
{
    private const MAX_IDS = 1000;

    /** Hide messages (soft archive). */
    public function trash(Request $request): JsonResponse
    {
        return $this->apply($request, now());
    }

    /** Un-hide messages. */
    public function restore(Request $request): JsonResponse
    {
        return $this->apply($request, null);
    }

    private function apply(Request $request, mixed $trashedAt): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'ids' => ['required', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['string'],
        ]);

        /** @var list<string> $ids */
        $ids = array_values(array_filter((array) $request->input('ids'), 'is_string'));

        $count = MailMessage::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->update(['trashed_at' => $trashedAt]);

        return response()->json(['updated' => $count]);
    }
}
