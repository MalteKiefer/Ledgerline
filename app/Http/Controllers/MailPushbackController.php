<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Support\Mail\ImapAppender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push an archived message BACK to its origin IMAP mailbox (IMAP APPEND). This
 * is the one deliberate write-to-origin path in the mail archive; everything
 * else is pull-only. It runs only on an explicit per-message user action.
 *
 * Zero-knowledge boundary: the server cannot read the sealed archive, so the
 * CLIENT decrypts the message and posts the plaintext RFC822 here purely so the
 * server can hand it to the IMAP server. Nothing is persisted, cached or logged
 * (Cache-Control: no-store; no message content in logs) — the same transient
 * cleartext pattern as receipt-OCR / paperless upload. Owner-scoped; the IMAP
 * host is SSRF-guarded inside ImapAppender.
 */
class MailPushbackController extends Controller
{
    private const MAX_BYTES = 52_428_800; // 50 MiB

    public function __invoke(Request $request, MailMessage $message, ImapAppender $appender): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($message->user_id !== $user->id) {
            abort(404);
        }

        $request->validate([
            'raw_b64' => ['required', 'string'],
            'folder' => ['sometimes', 'string', 'max:255'],
        ]);

        $raw = base64_decode($request->string('raw_b64')->value(), true);
        if ($raw === false || $raw === '') {
            abort(422, 'invalid message');
        }
        if (strlen($raw) > self::MAX_BYTES) {
            abort(413, 'message too large');
        }

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
            $appender->append($account, $folder, $raw, (string) $account->password);
        } catch (Throwable $e) {
            // Never surface IMAP internals to the client; log without content.
            Log::warning('mail.pushback.failed', ['account_id' => $account->id, 'message_id' => $message->id]);

            return response()->json(['error' => 'pushback_failed'], 502)
                ->header('Cache-Control', 'no-store');
        } finally {
            sodium_memzero($raw);
        }

        return response()->json(['pushed' => true])->header('Cache-Control', 'no-store');
    }
}
