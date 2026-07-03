<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One archived message. Metadata is stored here; the raw RFC822 message (.eml)
 * lives on the files disk at mail/{blob}. deleted_on_server_at marks a message
 * that vanished from the server but is kept locally.
 */
#[Fillable([
    'mail_account_id', 'mail_folder_id', 'uid', 'uidvalidity', 'message_id', 'subject',
    'from_name', 'from_email', 'to', 'date_at', 'seen', 'flagged', 'answered',
    'has_attachments', 'size', 'blob', 'preview', 'deleted_on_server_at', 'synced_at',
])]
class MailMessage extends Model
{
    protected function casts(): array
    {
        return [
            'uid' => 'integer',
            'uidvalidity' => 'integer',
            'to' => 'array',
            'date_at' => 'datetime',
            'seen' => 'boolean',
            'flagged' => 'boolean',
            'answered' => 'boolean',
            'has_attachments' => 'boolean',
            'size' => 'integer',
            'deleted_on_server_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MailFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MailFolder::class, 'mail_folder_id');
    }
}
