<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use App\Support\Mail\ImapDeleter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Delete an archived message from its origin IMAP mailbox (UID STORE \Deleted +
 * EXPUNGE, located by its stored Message-Id). The second deliberate,
 * destructive write-to-origin action; owner-scoped, on an explicit per-message
 * request (the SPA confirms first). Non-ZK: the server uses the row's
 * denormalised message_id — no client round-trip. The archived copy is kept
 * (this only removes the origin copy). `no-store`.
 */
class MailDeleteOriginController extends Controller
{
    public function __invoke(Request $request, MailMessage $message, ImapDeleter $deleter): JsonResponse
    {
        $this->authorizeOwner($request, $message);

        $account = $message->account;
        if ($account === null) {
            return $this->fail('account_deleted', 422);
        }

        $messageId = (string) $message->message_id;
        if (trim($messageId) === '') {
            return $this->fail('no_message_id', 422);
        }

        $folder = $request->filled('folder')
            ? $request->string('folder')->value()
            : (string) $message->folder;

        try {
            $expunged = $deleter->delete($account, $folder, $messageId, (string) $account->password);
        } catch (\Throwable $e) {
            Log::warning('mail.delete_origin.failed', ['message_id' => $message->id, 'error' => $e->getMessage()]);

            return $this->fail('delete_failed', 502);
        }

        // The archived copy is now the only one. Recorded so the reader can say
        // so rather than leaving the message looking like any other.
        $message->forceFill([
            'removed_from_server_at' => now(),
            'uid' => null,
            'uidvalidity' => null,
        ])->save();

        return response()->json(['ok' => true, 'expunged' => $expunged])->header('Cache-Control', 'no-store');
    }

    private function fail(string $detail, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'detail' => $detail], $status)->header('Cache-Control', 'no-store');
    }

    private function authorizeOwner(Request $request, MailMessage $message): void
    {
        abort_if((int) $message->user_id !== (int) $this->requireUser($request)->id, 404);
    }
}
