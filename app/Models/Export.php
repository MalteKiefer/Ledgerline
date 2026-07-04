<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's asynchronous download export. Built by a worker into one or more zip
 * parts on the files disk, listed on the Downloads page until it expires.
 */
#[Fillable([
    'user_id',
    'source',
    'variant',
    'title',
    'status',
    'item_count',
    'part_count',
    'total_size',
    'files',
    'payload',
    'error',
    'expires_at',
])]
class Export extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
            'part_count' => 'integer',
            'total_size' => 'integer',
            'files' => 'array',
            'payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<Export>  $query */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /** Not-yet-expired exports (the ones shown on the Downloads page). */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now()));
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * The zip parts as stored on disk.
     *
     * @return list<array{name: string, path: string, size: int}>
     */
    public function parts(): array
    {
        return array_values($this->files ?? []);
    }
}
