<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Mail\ImapCredentials;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * An IMAP mail account. Plain row except the login password, which is encrypted
 * at rest (usable in the clear at runtime for the connection + hourly sync).
 */
#[Fillable(['name', 'host', 'port', 'encryption', 'validate_cert', 'username', 'password', 'last_synced_at'])]
class MailAccount extends Model
{
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'validate_cert' => 'boolean',
            'password' => 'encrypted',
            'last_synced_at' => 'datetime',
        ];
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
            validateCert: $this->validate_cert,
        );
    }
}
