<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A time-limited public share of a single note.
 *
 * Holds a frozen server-rendered snapshot (plaintext title + markdown content)
 * plus the metadata needed to enforce expiry, a view limit and an optional
 * password. The password is stored only as a bcrypt hash.
 */
#[Fillable([
    'title',
    'content',
    'allow_download',
    'max_views',
    'expires_at',
])]
class NoteShare extends Model
{
    use HasUuids;

    protected $hidden = ['password_hash'];

    protected $casts = [
        'has_password' => 'boolean',
        'allow_download' => 'boolean',
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
