<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One archived message's metadata + sealed per-message content key. The raw
 * RFC822 bytes are sealed client/sealer-side (secretstream) and stored as a
 * content-addressed blob on the files disk (mail/{blob}, ledgered in
 * MailBlob) — this row never holds message content, only the hash that
 * addresses it and `sealed_key` (the hybrid-wrap envelope over the random
 * per-message symmetric key). `unique(user_id, content_hash)` de-duplicates
 * re-synced/identical messages per user. `user_id` is stamped from context
 * (AssignsOwner) — never fillable from request input.
 */
#[Fillable(['id', 'account_id', 'folder', 'content_hash', 'size', 'sealed_key', 'created_at'])]
class MailMessage extends Model
{
    use AssignsOwner;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
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
