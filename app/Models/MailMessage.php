<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One archived message's metadata + sealed per-message content key. The raw
 * RFC822 bytes are sealed client/sealer-side (secretstream) and stored as a
 * blob on the files disk at mail/{id} (ledgered in MailBlob under that SAME
 * uuid — see MaildirIngestor::ingestFile) — this row never holds message
 * content, only the hash that addresses it (dedup) and `sealed_key` (the
 * PQ-hybrid wrap envelope over the random per-message symmetric key). The
 * message's own `id` doubles as the blob's primary key: a client resolves
 * the sealed bytes for a listed message with `GET /mail/raw/{id}`, no extra
 * lookup needed. `unique(user_id, content_hash)` de-duplicates re-synced/
 * identical messages per user. `user_id` is stamped from context
 * (AssignsOwner) — never fillable from request input.
 *
 * @property ?Carbon $created_at
 * @property ?Carbon $trashed_at
 * @property bool $seen
 * @property ?int $account_id
 */
#[Fillable(['id', 'account_id', 'folder', 'seen', 'content_hash', 'size', 'sealed_key', 'envelope', 'envelope_key', 'created_at'])]
class MailMessage extends Model
{
    use AssignsOwner;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'trashed_at' => 'datetime', 'seen' => 'boolean'];
    }

    /** @return BelongsTo<MailAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
