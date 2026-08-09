<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use App\Support\BlobStore;
use App\Support\Mail\ImapAppender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Push an archived message BACK to its origin IMAP mailbox (APPEND). One of the
 * two deliberate write-to-origin actions; owner-scoped, on an explicit
 * per-message request (the SPA confirms first). Non-ZK: the server APPENDs its
 * OWN stored raw .eml — no client upload of plaintext. `no-store` so no
 * intermediary caches the request/response.
 */
class MailPushbackController extends Controller
{
    public function __invoke(Request $request, MailMessage $message, ImapAppender $appender): JsonResponse
    {
        $this->authorizeOwner($request, $message);

        $account = $message->account;
        if ($account === null) {
            return $this->fail('account_deleted', 422);
        }

        $raw = BlobStore::disk()->get('mail/'.$message->id);
        if (! is_string($raw) || $raw === '') {
            return $this->fail('raw_unavailable', 422);
        }

        $folder = $request->filled('folder')
            ? $request->string('folder')->value()
            : (string) $message->folder;

        try {
            $appender->append($account, $folder, $raw, (string) $account->password, (bool) $message->seen);
        } catch (\Throwable $e) {
            Log::warning('mail.pushback.failed', ['message_id' => $message->id, 'error' => $e->getMessage()]);

            return $this->fail('pushback_failed', 502);
        }

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
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
