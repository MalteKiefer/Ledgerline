<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use App\Services\Mail\ImapCredentials;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An IMAP mail account. Plain row except the login password, which is encrypted
 * at rest (usable in the clear at runtime for the connection + hourly sync).
 */
#[Fillable(['name', 'host', 'port', 'encryption', 'validate_cert', 'username', 'password', 'last_synced_at', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'from_name', 'reply_to', 'signature'])]
class MailAccount extends Model
{
    use OwnsUserData;

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'validate_cert' => 'boolean',
            'password' => 'encrypted',
            'last_synced_at' => 'datetime',
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
        ];
    }

    /**
     * Build the outbound (SMTP) config, falling back to the IMAP login when the
     * SMTP-specific host/credentials are blank.
     *
     * @return array<string,mixed>
     */
    public function smtpConfig(): array
    {
        return [
            'host' => $this->smtp_host ?: $this->host,
            'port' => $this->smtp_port ?: 587,
            'encryption' => $this->smtp_encryption ?: 'starttls',
            'username' => $this->smtp_username ?: $this->username,
            'password' => (string) ($this->smtp_password !== null && $this->smtp_password !== '' ? $this->smtp_password : $this->password),
            'from_name' => $this->from_name,
            'from_email' => $this->username,
            'reply_to' => $this->reply_to,
        ];
    }

    /**
     * Whether this account can send: a usable SMTP host, username and password
     * (the IMAP login is the fallback for each). Guards the compose UI and the
     * send endpoint so an unconfigured account produces a clear warning rather
     * than an opaque transport failure.
     */
    public function smtpConfigured(): bool
    {
        $cfg = $this->smtpConfig();

        return ($cfg['host'] ?? '') !== '' && ($cfg['username'] ?? '') !== '' && ($cfg['password'] ?? '') !== '';
    }

    /**
     * Sender identities for this account, default first then oldest.
     *
     * @return HasMany<MailIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(MailIdentity::class)->orderByDesc('is_default')->orderBy('id');
    }

    /**
     * The default identity (the one flagged default, else the first). Null only
     * for a freshly created account before its default identity is seeded.
     */
    public function defaultIdentity(): ?MailIdentity
    {
        return $this->identities()->first();
    }

    /** Build the IMAP credentials value object for the reader/stats services. */
    public function credentials(): ImapCredentials
    {
        return new ImapCredentials(
            host: $this->host,
            port: $this->port,
            encryption: $this->encryption,
            username: $this->username,
            password: (string) $this->password,
            validateCert: (bool) ($this->validate_cert ?? true),
        );
    }
}
