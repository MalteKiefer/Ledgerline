<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A public, tokenised read-only download link to one stored file. */
#[Fillable(['token', 'stored_file_id', 'expires_at', 'password', 'downloads'])]
class FilePublicLink extends Model
{
    use OwnsUserData;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'password' => 'hashed',
            'downloads' => 'integer',
        ];
    }

    /** @return BelongsTo<StoredFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isProtected(): bool
    {
        return $this->password !== null && $this->password !== '';
    }
}
