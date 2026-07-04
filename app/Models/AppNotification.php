<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A local, in-app notification shown in the bell menu (and mirrored to a browser
 * / desktop notification while the app is open). Generalised by category; only
 * backups create them for now.
 */
#[Fillable(['user_id', 'level', 'category', 'title', 'body', 'read_at'])]
class AppNotification extends Model
{
    use OwnsUserData;

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * Record a notification for every user (single-tenant: usually one). Safe to
     * call from queued jobs — a failure here must never break the caller.
     */
    public static function record(int $userId, string $level, string $title, ?string $body = null, string $category = 'general'): void
    {
        try {
            static::create([
                'user_id' => $userId,
                'level' => $level,
                'category' => $category,
                'title' => $title,
                'body' => $body,
            ]);
        } catch (\Throwable) {
            // Notifications are best-effort; never propagate.
        }
    }

    /**
     * Record the same notification for several users (a workspace-infra event
     * fanned out to the admins).
     *
     * @param  iterable<int>  $userIds
     */
    public static function recordFor(iterable $userIds, string $level, string $title, ?string $body = null, string $category = 'general'): void
    {
        foreach ($userIds as $userId) {
            static::record((int) $userId, $level, $title, $body, $category);
        }
    }
}
