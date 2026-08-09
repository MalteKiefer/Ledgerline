<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Database\Factories\MailAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string $username
 * @property ?string $password
 * @property string $encryption
 * @property ?string $smtp_host
 * @property ?int $smtp_port
 * @property ?string $smtp_username
 * @property ?string $smtp_password
 * @property ?string $smtp_encryption
 * @property ?string $from_name
 * @property ?string $from_email
 * @property ?array<int,string> $folders
 * @property ?Carbon $backfill_since
 * @property bool $delete_after_import
 * @property bool $skip_spam
 * @property bool $enabled
 * @property ?int $sync_interval_minutes
 * @property string $status
 * @property ?string $sync_batch_id
 * @property ?string $last_error
 * @property ?Carbon $last_synced_at
 * @property ?int $messages_count
 */
#[Fillable([
    'name', 'host', 'port', 'username', 'password', 'encryption',
    // Per-account SMTP for compose/reply/forward (smtp_password is the one new
    // encrypted-at-rest secret; from_name/from_email set the composed From).
    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'from_name', 'from_email',
    'folders', 'backfill_since', 'delete_after_import', 'skip_spam', 'enabled', 'status', 'last_error', 'last_synced_at',
    'sync_interval_minutes',
])]
// Defence in depth on top of the API controllers' explicit present() arrays
// (which never add these keys): even a stray toArray()/toJson() can never leak
// the IMAP or SMTP password.
#[Hidden(['password', 'smtp_password'])]
class MailAccount extends Model
{
    use AssignsOwner;

    /** @use HasFactory<MailAccountFactory> */
    use HasFactory;

    public const ENCRYPTIONS = ['ssl', 'tls', 'starttls', 'none'];

    public const STATUSES = ['idle', 'syncing', 'error'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'smtp_password' => 'encrypted',
            'smtp_port' => 'integer',
            'folders' => 'array',
            'backfill_since' => 'date',
            'delete_after_import' => 'boolean',
            'skip_spam' => 'boolean',
            'enabled' => 'boolean',
            'last_synced_at' => 'datetime',
            'sync_interval_minutes' => 'integer',
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

    /**
     * True when this account has enough SMTP configuration to send: a host and
     * a From address. Gates the compose/reply/forward endpoints (else no_smtp).
     */
    public function hasSmtp(): bool
    {
        $host = is_string($this->smtp_host) ? trim($this->smtp_host) : '';
        $from = is_string($this->from_email) ? trim($this->from_email) : '';

        return $host !== '' && $from !== '';
    }

    /**
     * Effective fetch interval in minutes: the per-account override when set,
     * otherwise the workspace default (config('mail_archive.sync_interval_minutes')).
     */
    public function effectiveSyncIntervalMinutes(): int
    {
        $override = $this->sync_interval_minutes;
        if (is_int($override) && $override > 0) {
            return $override;
        }

        $default = config('mail_archive.sync_interval_minutes', 30);

        return max(1, is_numeric($default) ? (int) $default : 30);
    }

    /**
     * True when this account is due for a fetch — never synced, or the last
     * sync is at least its effective interval ago.
     */
    public function isDueForSync(\DateTimeInterface $now): bool
    {
        $last = $this->last_synced_at;
        if ($last === null) {
            return true;
        }

        return $last->copy()->addMinutes($this->effectiveSyncIntervalMinutes())->lessThanOrEqualTo($now);
    }
}
