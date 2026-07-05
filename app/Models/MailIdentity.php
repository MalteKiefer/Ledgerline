<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sender identity belonging to a mail account: a From address with its own
 * display name, reply-to and signature (Thunderbird-style). Identities are
 * reached only through an owned account, so ownership is enforced at the
 * controller via the parent account's isOwnedBy() — there is no user_id column.
 */
#[Fillable(['mail_account_id', 'from_name', 'from_email', 'reply_to', 'signature', 'is_default'])]
class MailIdentity extends Model
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<MailAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }
}
