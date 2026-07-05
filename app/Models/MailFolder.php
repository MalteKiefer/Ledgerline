<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A synced IMAP folder in the local archive, private to its owning user. */
#[Fillable(['user_id', 'mail_account_id', 'path', 'name', 'delimiter', 'role', 'uidvalidity'])]
class MailFolder extends Model
{
    use OwnsUserData;

    protected function casts(): array
    {
        return ['uidvalidity' => 'integer'];
    }

    /** @return BelongsTo<MailAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }
}
