<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\Mail\WriteBackMailFlags;
use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Star messages (the IMAP \Flagged flag).
 *
 * The column has existed since the archive was created and reached neither the
 * API nor the interface, so a mail could be found but not set aside. Chunked and
 * change-filtered for the same reason as the read flag: an archived row carries
 * its full search text, so Postgres rarely gets a heap-only update out of a flag
 * change and every one rewrites all seven indexes.
 *
 * Local for now. Once the mailbox is written back to (UID STORE), this becomes
 * the local half of a two-way flag and the mailbox owns the truth.
 */
class MailFlagController extends Controller
{
    private const MAX_IDS = 1000;

    private const CHUNK = 100;

    public function update(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'ids' => ['required', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['string'],
            'flagged' => ['required', 'boolean'],
        ]);

        /** @var list<string> $ids */
        $ids = array_values(array_filter((array) $request->input('ids'), 'is_string'));
        $flagged = $request->boolean('flagged');

        $count = 0;
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $count += MailMessage::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $chunk)
                ->where('flagged', '!=', $flagged)
                ->update(['flagged' => $flagged]);
        }

        // Carry it back to the mailbox, so the archive and the phone's mail app
        // do not give two different answers. Queued and best effort: the local
        // change is already committed, and messages without an origin reference
        // are skipped rather than guessed at.
        WriteBackMailFlags::queueFor((int) $user->id, $ids, 'flagged', $flagged);

        return response()->json(['updated' => $count]);
    }
}
