<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use App\Support\BlobStore;
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
    'format',
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
    'seen_at',
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
     * Ready exports the user has not seen on the Downloads page yet. Rendered
     * as a badge on the nav so a finished background export is noticed on the
     * next page load without polling.
     */
    /** Nav badge count, rendered 3× per page (desktop nav, mobile strip, mobile
     *  drawer). Memoised in the container: per-request in prod, reset between
     *  tests. */
    private static function unseenMemoKey(int $userId): string
    {
        return 'memo.export.unseen.'.$userId;
    }

    public static function unseenReadyCount(int $userId): int
    {
        $key = self::unseenMemoKey($userId);
        if (! app()->bound($key)) {
            app()->instance($key, static::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('status', 'ready')
                ->whereNull('seen_at')
                ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now()))
                ->count());
        }

        return app($key);
    }

    /** Mark all of the user's ready exports as seen (Downloads page visited). */
    public static function markSeenFor(int $userId): void
    {
        static::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('status', 'ready')
            ->whereNull('seen_at')
            ->update(['seen_at' => Carbon::now()]);
        // The badge count just changed; drop the memo so a same-request re-read
        // (the layout renders after the Downloads controller) reflects it.
        app()->forgetInstance(self::unseenMemoKey($userId));
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
            'seen_at' => 'datetime',
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
        $disk = BlobStore::disk();
        foreach ($this->parts() as $part) {
            $disk->delete($part['path']);
        }
        $this->delete();
    }
}
