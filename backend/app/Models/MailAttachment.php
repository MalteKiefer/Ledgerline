<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One MIME attachment part of an archived message. The decoded bytes live
 * plaintext on the files disk at mail/att/{blob} (ledgered in MailBlob under
 * that same uuid with kind=attachment). Extracted once at ingest so the reader
 * can list / view / save attachments and inline cid: images without re-parsing.
 *
 * Server-set only (forceFill); nothing is mass-assignable. `user_id` is stamped
 * from context (AssignsOwner). Immutable — deleted only when the parent message
 * is force-purged (FK cascade) or on a full user delete.
 *
 * @property string $id
 * @property string $message_id
 * @property int $user_id
 * @property string $blob
 * @property ?string $filename
 * @property ?string $content_type
 * @property ?string $content_id
 * @property bool $inline
 * @property int $size
 * @property ?Carbon $created_at
 */
class MailAttachment extends Model
{
    use AssignsOwner;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    /** Server-set via forceFill only. */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'inline' => 'boolean',
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<MailMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
