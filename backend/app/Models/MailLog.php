<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A per-account mail sync/ingest diagnostic log line. Metadata only — never
 * message content. Written by the sync pipeline (SyncMailAccount /
 * IngestMailChunk / MbsyncRunner) via App\Support\Mail\MailLogger and shown to
 * the account owner so they can see which folders synced and diagnose errors.
 *
 * @property int $id
 * @property int $account_id
 * @property int $user_id
 * @property string $level
 * @property string $event
 * @property ?string $folder
 * @property ?string $message
 * @property ?Carbon $created_at
 */
#[Fillable(['account_id', 'user_id', 'level', 'event', 'folder', 'message', 'created_at'])]
class MailLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<MailAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'account_id');
    }
}
