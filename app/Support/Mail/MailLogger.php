<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use App\Models\MailLog;
use App\Support\Redactor;
use Throwable;

/**
 * Writes per-account mail sync/ingest diagnostic log lines. Best-effort: a
 * logging failure must NEVER break the sync (wrapped in try/catch). The stored
 * text is redacted (Bearer/token=/key= stripped) and length-capped, and is
 * metadata only — never message content, subjects or addresses. Owner
 * (user_id) is taken from the account, so lines are always owner-scoped.
 */
class MailLogger
{
    private const LEVELS = ['info', 'warn', 'error'];

    private const MAX_MESSAGE = 1000;

    public static function record(MailAccount $account, string $level, string $event, ?string $folder = null, ?string $message = null): void
    {
        try {
            $lvl = in_array($level, self::LEVELS, true) ? $level : 'info';
            $msg = $message === null ? null : mb_substr(Redactor::redact($message), 0, self::MAX_MESSAGE);

            MailLog::query()->create([
                'account_id' => $account->id,
                'user_id' => $account->user_id,
                'level' => $lvl,
                'event' => mb_substr($event, 0, 64),
                'folder' => $folder === null ? null : mb_substr($folder, 0, 255),
                'message' => $msg,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Never let diagnostics break the sync.
        }
    }
}
