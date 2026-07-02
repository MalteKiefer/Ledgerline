<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A time-limited public share of a single note.
 *
 * Holds only the client-encrypted snapshot ciphertext and the metadata the
 * server needs to enforce expiry and view limits. The plaintext note, its
 * title and the decryption key never reach the server.
 */
#[Fillable([
    'cipher',
    'nonce',
    'has_password',
    'wrapped_key',
    'wrap_salt',
    'wrap_nonce',
    'wrap_ops',
    'wrap_mem',
    'max_views',
    'expires_at',
])]
class NoteShare extends Model
{
    use HasUuids;

    protected $casts = [
        'has_password' => 'boolean',
        'views' => 'integer',
        'max_views' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * A share is gone once it has expired or reached its view limit.
     */
    public function isGone(): bool
    {
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return true;
        }

        return $this->max_views !== null && $this->views >= $this->max_views;
    }
}
