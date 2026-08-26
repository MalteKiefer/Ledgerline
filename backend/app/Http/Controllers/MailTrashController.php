<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\Mail\WriteBackMailMove;
use App\Models\MailAccount;
use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Throwing archived mail away — here and, unless the account says otherwise, on
 * the server too.
 *
 * The archive row is NEVER destroyed: "trash" stamps trashed_at and "restore"
 * clears it, exactly as before. What is new is that the mailbox follows: the
 * message is moved into the account's trash folder on the origin server, and
 * restoring moves it back where it came from. Without that half, deleting a
 * mail here left it sitting in the phone's inbox, which is not what deleting
 * means to anyone.
 *
 * The two halves are deliberately not atomic and the local one goes first: the
 * archived copy is the one that must never be lost, so it is committed before
 * anything is asked of a server that may be down. A move that fails is a log
 * line on the account, not a lost click.
 */
class MailTrashController extends Controller
{
    private const MAX_IDS = 1000;

    /** Hide messages here, and move them to the mailbox's trash folder. */
    public function trash(Request $request): JsonResponse
    {
        return $this->apply($request, now());
    }

    /** Un-hide them, and move them back out of the trash folder. */
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

        // Read where each message currently lives BEFORE the local update, so a
        // restore knows which folder to put it back into.
        $rows = MailMessage::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->get(['id', 'account_id', 'folder', 'restore_folder']);

        $count = MailMessage::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->update($trashedAt === null
                ? ['trashed_at' => null]
                // Remember where it was, so restoring is not a guess at INBOX.
                : ['trashed_at' => $trashedAt]);

        $this->carryToMailbox((int) $user->id, $rows, $trashedAt !== null);

        return response()->json(['updated' => $count]);
    }

    /**
     * @param  Collection<int, MailMessage>  $rows
     * @return list<string>
     */
    private function idsOf(Collection $rows): array
    {
        return array_values($rows->map(fn (MailMessage $m): string => (string) $m->id)->all());
    }

    /**
     * Queue the matching move on the origin server, one target folder at a time.
     *
     * @param  Collection<int, MailMessage>  $rows
     */
    private function carryToMailbox(int $userId, Collection $rows, bool $toTrash): void
    {
        $accounts = MailAccount::query()
            ->whereIn('id', $rows->pluck('account_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($rows->groupBy('account_id') as $accountId => $group) {
            $account = $accounts->get($accountId);
            if ($account === null || ! $account->write_back_deletes) {
                continue;
            }

            $trashFolder = trim((string) ($account->trash_folder ?? '')) ?: 'Trash';

            if ($toTrash) {
                // Note where each one came from before it leaves, then move it.
                foreach ($group as $row) {
                    if ($row->folder !== $trashFolder) {
                        $row->forceFill(['restore_folder' => $row->folder])->save();
                    }
                }
                WriteBackMailMove::queueFor($userId, $this->idsOf($group), $trashFolder);

                continue;
            }

            // Restoring: back to wherever each one was, so messages that came
            // from different folders do not all land in the same one.
            foreach ($group->groupBy(fn (MailMessage $m): string => (string) ($m->restore_folder ?? 'INBOX')) as $target => $back) {
                WriteBackMailMove::queueFor($userId, $this->idsOf($back), (string) $target);
            }
        }
    }
}
