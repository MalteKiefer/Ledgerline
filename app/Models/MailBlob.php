<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ownership ledger for a stored raw mail blob (mail/{blob}) — the plaintext
 * RFC822 bytes. One row per blob a message references; drives quota,
 * owner-scoped access, and lets mail:sweep-orphans reclaim bytes no message
 * row references anymore. Mirrors the OLD MailBlob (server-set only —
 * forceFill; nothing mass-assignable).
 *
 * @property string $blob
 * @property int $user_id
 * @property int $size
 * @property ?Carbon $created_at
 */
class MailBlob extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'blob';

    protected $keyType = 'string';

    /** Server-set via forceFill only. */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'size' => 'integer'];
    }
}
