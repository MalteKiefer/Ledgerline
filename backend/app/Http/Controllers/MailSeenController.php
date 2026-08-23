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
 * Stamps seen_at when marking read (cleared when marking unread).
 */
class MailSeenController extends Controller
{
    private const MAX_IDS = 1000;

    /**
     * Rows per statement.
     *
     * An archived message carries its full search text, so the row is large and
     * Postgres rarely gets a heap-only update out of a flag change: every one
     * rewrites all seven indexes, the 42 MB GIN among them. Marking fifty as
     * read measured 5.4 s in one statement, and a few hundred ran past the
     * proxy's patience as a 502. Chunking does not make the work smaller, but it
     * keeps each statement short.
     */
    private const CHUNK = 100;

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
        $seen = $request->boolean('seen');

        $count = 0;
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $count += MailMessage::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $chunk)
                // Only the rows that actually change. Marking a selection read
                // usually finds most of it already read, and rewriting a row to
                // the value it already holds costs the same as a real change.
                ->where('seen', '!=', $seen)
                ->update(['seen' => $seen, 'seen_at' => $seen ? now() : null]);
        }

        return response()->json(['updated' => $count]);
    }
}
