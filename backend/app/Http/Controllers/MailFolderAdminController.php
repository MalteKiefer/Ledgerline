<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Support\Mail\ImapFolders;
use App\Support\Redactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The folders that exist on the mailbox, and creating, renaming and removing
 * them.
 *
 * Distinct from MailFolderController, which lists the folders the ARCHIVE has
 * mail in. That list is derived from messages, so an empty folder is invisible
 * in it and a new folder cannot be filed into until something is already there.
 * This one asks the server.
 *
 * Inline rather than queued: a folder list is what the picker is waiting on,
 * and creating one is immediately followed by using it. The connection is short
 * and bounded like the connection test — the long-running paths (sync, moves)
 * are the ones that belong on the queue.
 */
class MailFolderAdminController extends Controller
{
    public function index(Request $request, ImapFolders $folders): JsonResponse
    {
        $account = $this->account($request);

        try {
            $list = $folders->list($account, (string) $account->password);
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        return response()->json(['folders' => $list])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, ImapFolders $folders): JsonResponse
    {
        $account = $this->account($request);
        $name = $this->folderName($request, 'name');

        try {
            $folders->create($account, $name, (string) $account->password);
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        return response()->json(['ok' => true, 'name' => $name])->header('Cache-Control', 'no-store');
    }

    public function rename(Request $request, ImapFolders $folders): JsonResponse
    {
        $account = $this->account($request);
        $from = $this->folderName($request, 'from');
        $to = $this->folderName($request, 'to');

        try {
            $folders->rename($account, $from, $to, (string) $account->password);
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        // Keep the archive pointing at where its mail now lives; without this
        // the rows would name a folder the server no longer has.
        MailMessage::query()
            ->where('user_id', $account->user_id)
            ->where('account_id', $account->id)
            ->where('folder', $from)
            ->update(['folder' => $to]);

        return response()->json(['ok' => true, 'name' => $to])->header('Cache-Control', 'no-store');
    }

    /**
     * Remove a folder. Refused while it still holds mail — IMAP's DELETE takes
     * the messages with it, and that cannot be undone on the server.
     */
    public function destroy(Request $request, ImapFolders $folders): JsonResponse
    {
        $account = $this->account($request);
        $name = $this->folderName($request, 'name');

        try {
            $folders->delete($account, $name, (string) $account->password);
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }

    private function account(Request $request): MailAccount
    {
        $user = $this->requireUser($request);
        $request->validate([
            'account_id' => ['required', 'integer', Rule::exists('mail_accounts', 'id')->where('user_id', $user->id)],
        ]);

        /** @var MailAccount $account */
        $account = MailAccount::query()
            ->where('user_id', $user->id)
            ->findOrFail($request->integer('account_id'));

        return $account;
    }

    /**
     * A folder name that is safe to put on an IMAP command line.
     *
     * The client refuses control characters too, but this is the side that
     * matters: a newline in a folder name is a second command.
     */
    private function folderName(Request $request, string $key): string
    {
        $request->validate([
            $key => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1f\x7f]/'],
        ]);

        $name = trim($request->string($key)->value());
        abort_if($name === '', 422);

        return $name;
    }

    private function failed(Throwable $e): JsonResponse
    {
        // The message is the server's own words — it says why far better than
        // "failed" does — but it goes through the redactor first, because a
        // transport error can quote what we sent.
        return response()->json([
            'ok' => false,
            'detail' => Redactor::redact($e->getMessage()),
        ], 502)->header('Cache-Control', 'no-store');
    }
}
