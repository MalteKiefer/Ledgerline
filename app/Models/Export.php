<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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
    use OwnsUserData;

    /** Most exports one user may have building (queued/processing) at once. */
    public const MAX_IN_FLIGHT = 3;

    /**
     * Count the user's exports still building (queued or processing). Used to cap
     * how many heavy exports a single user can have in the queue at once.
     */
    public static function inFlightCount(int $userId): int
    {
        return static::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereIn('status', ['queued', 'processing'])
            ->count();
    }

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

    /** Delete the export and all its zip parts from the files disk. */
    public function purge(): void
    {
        $disk = Storage::disk(config('files.disk'));
        foreach ($this->parts() as $part) {
            $disk->delete($part['path']);
        }
        $this->delete();
    }
}
