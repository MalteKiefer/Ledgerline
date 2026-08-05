<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mark archived messages read/unread. The `seen` flag is initially derived from
 * the origin Maildir (\Seen), but the owner can override it — individually or in
 * bulk. Owner-scoped; metadata-only (never touches content or the origin).
 */
class MailSeenController extends Controller
{
    private const MAX_IDS = 1000;

    public function update(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'ids' => ['required', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['string'],
            'seen' => ['required', 'boolean'],
        ]);

        /** @var list<string> $ids */
        $ids = array_values(array_filter((array) $request->input('ids'), 'is_string'));

        $count = MailMessage::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->update(['seen' => $request->boolean('seen')]);

        return response()->json(['updated' => $count]);
    }
}
