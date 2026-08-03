<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-folder IMAP sync cursor for a mail account — composite primary key
 * (account_id, folder), one row per folder being synced. Tracks UIDVALIDITY
 * (reset detection), the highest UID fetched so far, and CONDSTORE
 * HIGHMODSEQ where the server supports it. No user_id column: ownership
 * flows through the parent account.
 *
 * CURRENTLY UNUSED / reserved scaffolding: nothing in the sync pipeline reads
 * or writes this table today. The live resume anchors are mbsync's own
 * UID/UIDVALIDITY state FILES on disk (see MbsyncRunner's stateDir) plus the
 * ledger's (user_id, content_hash) dedup in MaildirIngestor — resumability
 * does not depend on these columns being kept current. Do not treat a stale
 * or empty row here as evidence of anything; this model exists for a future
 * explicit server-side cursor, not as the current source of truth.
 *
 * Eloquent has no native composite-primary-key support, so save()'s
 * update/select paths are pointed at both columns via the two overrides
 * below instead of a single (non-existent) `id` key. Callers should still
 * prefer `updateOrCreate(['account_id' => ..., 'folder' => ...], $values)`,
 * which matches/creates by those two columns directly and upserts without
 * duplicating a row.
 *
 * @property int $account_id
 * @property string $folder
 * @property ?int $uidvalidity
 * @property int $highest_uid
 * @property ?int $highmodseq
 * @property ?Carbon $updated_at
 */
#[Fillable(['account_id', 'folder', 'uidvalidity', 'highest_uid', 'highmodseq', 'updated_at'])]
class MailSyncState extends Model
{
    protected $table = 'mail_sync_state';

    public $incrementing = false;

    /** No created_at column on this table — only updated_at is tracked. */
    const CREATED_AT = null;

    protected function casts(): array
    {
        return ['updated_at' => 'datetime'];
    }

    /** @return BelongsTo<MailAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'account_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSelectQuery($query)
    {
        return $query
            ->where('account_id', '=', $this->original['account_id'] ?? $this->getAttribute('account_id'))
            ->where('folder', '=', $this->original['folder'] ?? $this->getAttribute('folder'));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSaveQuery($query)
    {
        return $this->setKeysForSelectQuery($query);
    }
}
