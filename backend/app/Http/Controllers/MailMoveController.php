<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\Mail\WriteBackMailMove;
use App\Models\MailAccount;
use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Move archived mail into another folder — here and on the origin server.
 *
 * Filing mail is half of what a mail client is for, and until now the folder a
 * message sat in was whatever the sync had found: readable, not changeable. The
 * archive row follows the move so the reader shows it where it now lives, and
 * the mailbox follows so the phone agrees.
 *
 * Same order as the trash: local first, server after, on the queue. The row is
 * the copy that must never be lost, so it is never waiting on a mailbox.
 *
 * Junk is deliberately not a separate action: marking spam IS moving to the
 * junk folder, and giving it its own endpoint would mean two ways to do one
 * thing that could disagree.
 */
class MailMoveController extends Controller
{
    private const MAX_IDS = 1000;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'ids' => ['required', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['string'],
            // A folder name reaches an IMAP command line. The client refuses
            // control characters as well, but this is the side that matters.
            'folder' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1f\x7f]/'],
        ]);

        /** @var list<string> $ids */
        $ids = array_values(array_filter((array) $request->input('ids'), 'is_string'));
        $target = trim($request->string('folder')->value());
        if ($target === '') {
            return response()->json(['error' => 'empty_folder'], 422);
        }

        $rows = MailMessage::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->get(['id', 'account_id', 'folder']);

        if ($rows->isEmpty()) {
            return response()->json(['updated' => 0]);
        }

        $accounts = MailAccount::query()
            ->whereIn('id', $rows->pluck('account_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($rows->groupBy('account_id') as $accountId => $group) {
            $account = $accounts->get($accountId);
            // Same switch as deleting: this takes the message out of the folder
            // it is in on the server, which is a change to the mailbox.
            if ($account === null || ! $account->write_back_deletes) {
                continue;
            }

            WriteBackMailMove::queueFor(
                (int) $user->id,
                array_values($group->map(fn (MailMessage $m): string => (string) $m->id)->all()),
                $target,
            );
        }

        // After queueing, not before: the job works out which server folder to
        // take the messages OUT of by reading the rows, and this update is what
        // changes that. Queueing is not running, so nothing has happened on the
        // far side yet either way. Moving somewhere is not trashing, so
        // trashed_at is left alone.
        $count = MailMessage::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $rows->pluck('id')->all())
            ->update(['folder' => $target]);

        return response()->json(['updated' => $count]);
    }
}
