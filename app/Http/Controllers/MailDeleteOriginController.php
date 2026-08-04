<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Support\Mail\ImapDeleter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delete an archived message from its ORIGIN IMAP mailbox (IMAP UID STORE
 * \Deleted + EXPUNGE), by its RFC822 Message-Id. This is a deliberate,
 * destructive write-to-origin path (like push-back) — the mail archive is
 * otherwise pull-only. It runs only on an explicit, confirmed user action and
 * frees space on the upstream server AFTER a message has been archived; it
 * works for any archived message regardless of when it was imported.
 *
 * Zero-knowledge boundary: the server cannot read the sealed archive, so the
 * CLIENT decrypts the message and posts only its (non-secret) Message-Id here
 * so the server can locate + delete it on the origin. Nothing is persisted,
 * cached or logged (Cache-Control: no-store; no message content in logs).
 * Owner-scoped; the IMAP host is SSRF-guarded inside ImapDeleter.
 *
 * The IMMUTABLE local archive copy is NOT touched — only the origin server's
 * copy is removed. The archived, sealed blob remains readable.
 */
class MailDeleteOriginController extends Controller
{
    public function __invoke(Request $request, MailMessage $message, ImapDeleter $deleter): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($message->user_id !== $user->id) {
            abort(404);
        }

        $request->validate([
            'message_id' => ['required', 'string', 'max:998'],
            'folder' => ['sometimes', 'string', 'max:255'],
        ]);

        $account = MailAccount::query()
            ->where('id', $message->account_id)
            ->where('user_id', $user->id)
            ->first();
        if ($account === null) {
            abort(404);
        }

        $requestedFolder = $request->string('folder')->value();
        $folder = $requestedFolder !== '' ? $requestedFolder : (string) $message->folder;

        try {
            // $account->password decrypts via the model's `encrypted` cast.
            $deleted = $deleter->delete($account, $folder, $request->string('message_id')->value(), (string) $account->password);
        } catch (Throwable $e) {
            // Never surface IMAP internals to the client; log without content.
            Log::warning('mail.delete_origin.failed', ['account_id' => $account->id, 'message_id' => $message->id]);

            return response()->json(['error' => 'delete_failed'], 502)
                ->header('Cache-Control', 'no-store');
        }

        return response()->json(['deleted' => $deleted])->header('Cache-Control', 'no-store');
    }
}
