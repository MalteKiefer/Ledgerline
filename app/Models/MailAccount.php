<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Database\Factories\MailAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A user's configured IMAP mail account (host/credentials + which folders to
 * sync). The password is the one plaintext secret the server must hold to run
 * the sync — `encrypted` at rest via APP_KEY, never rendered back to the
 * client. `user_id` is stamped from the authenticated user (AssignsOwner) —
 * never fillable from request input, so one user can never mass-assign an
 * account onto another.
 *
 * @property ?array<int,string> $folders
 * @property ?Carbon $backfill_since
 * @property ?Carbon $last_synced_at
 */
#[Fillable([
    'name', 'host', 'port', 'username', 'password', 'encryption',
    'folders', 'backfill_since', 'enabled', 'status', 'last_error', 'last_synced_at',
])]
class MailAccount extends Model
{
    use AssignsOwner;

    /** @use HasFactory<MailAccountFactory> */
    use HasFactory;

    public const ENCRYPTIONS = ['ssl', 'tls', 'starttls', 'none'];

    public const STATUSES = ['idle', 'syncing', 'error'];

    /**
     * Defence in depth on top of the API controllers' explicit present()
     * arrays (which never add this key in the first place): even a stray
     * array()/toJson() call on the model elsewhere in the codebase can never
     * leak the password.
     *
     * @var list<string>
     */
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'folders' => 'array',
            'backfill_since' => 'date',
            'enabled' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<MailMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class, 'account_id');
    }
}
