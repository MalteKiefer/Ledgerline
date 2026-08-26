<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One archived message. The raw RFC822 bytes live plaintext on the files disk
 * at mail/{id} (ledgered in MailBlob under that SAME uuid — the message's own
 * `id` doubles as the blob key, so a client fetches the raw .eml with
 * `GET /mail/raw/{id}` with no extra lookup). This row holds the denormalised
 * envelope + a plaintext text body + server-sanitised HTML + auth/spam signals
 * + the full-text search column; the raw .eml blob stays authoritative.
 *
 * IMMUTABLE: never edited after ingest — only `seen`/`trashed_at` toggle (via
 * targeted bulk query updates, not model mass-assignment). Every column is set
 * server-side by MaildirIngestor via forceFill; there is no mass-assignable
 * fillable surface. `unique(user_id, content_hash)` de-duplicates re-synced /
 * identical messages per user. `user_id` is stamped from context (AssignsOwner).
 *
 * @property string $id
 * @property int $user_id
 * @property ?int $account_id
 * @property string $folder
 * @property ?int $uid Origin IMAP UID, null when the message did not come from a sync.
 * @property ?int $uidvalidity Generation the UID belongs to; a UID without it is meaningless.
 * @property string $content_hash
 * @property int $size
 * @property ?string $message_id
 * @property ?string $in_reply_to
 * @property ?string $references
 * @property ?string $thread_id
 * @property ?string $subject
 * @property ?string $from_name
 * @property ?string $from_email
 * @property ?array<int, array{name:?string, email:string}> $to_json
 * @property ?array<int, array{name:?string, email:string}> $cc_json
 * @property ?string $reply_to
 * @property ?Carbon $date
 * @property bool $has_attachment
 * @property int $attachment_count
 * @property ?string $text_body
 * @property ?string $html_sanitized
 * @property bool $spam
 * @property ?string $spf
 * @property ?string $dkim
 * @property ?string $dmarc
 * @property ?string $encrypted_type
 * @property ?string $decrypt_status
 * @property bool $seen
 * @property bool $flagged
 * @property bool $answered
 * @property ?Carbon $seen_at
 * @property ?Carbon $trashed_at
 * @property ?Carbon $created_at
 * @property ?string $search_text
 * @property ?Carbon $indexed_at
 */
#[Hidden(['search_text'])]
class MailMessage extends Model
{
    use AssignsOwner;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    /** Every column is server-set via forceFill; nothing is mass-assignable. */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'trashed_at' => 'datetime',
            'seen_at' => 'datetime',
            'date' => 'datetime',
            'indexed_at' => 'datetime',
            'seen' => 'boolean',
            'flagged' => 'boolean',
            'answered' => 'boolean',
            'spam' => 'boolean',
            'has_attachment' => 'boolean',
            'attachment_count' => 'integer',
            'uid' => 'integer',
            'uidvalidity' => 'integer',
            'size' => 'integer',
            'to_json' => 'array',
            'cc_json' => 'array',
        ];
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

    /** @return HasMany<MailAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class, 'message_id');
    }

    /** @return BelongsToMany<MailLabel, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(MailLabel::class, 'mail_label_message', 'mail_message_id', 'mail_label_id');
    }
}
