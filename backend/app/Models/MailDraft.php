<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Server-side autosave state for a mail composition; never an archive message. */
class MailDraft extends Model
{
    use HasUuids;
    use OwnsUserData;

    protected $fillable = [
        'mail_account_id', 'mode', 'source_message_id', 'to', 'cc', 'bcc', 'subject',
        'text_body', 'html_body', 'mail_signature_id', 'sent_folder', 'file_ids',
        'gallery_photo_ids', 'local_attachments', 'read_receipt', 'high_priority',
    ];

    protected function casts(): array
    {
        return [
            'to' => 'array', 'cc' => 'array', 'bcc' => 'array', 'file_ids' => 'array',
            'gallery_photo_ids' => 'array', 'local_attachments' => 'array',
            'read_receipt' => 'boolean', 'high_priority' => 'boolean',
        ];
    }
}
