<?php

declare(strict_types=1);

namespace App\Models;

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
    public static function record(string $level, string $title, ?string $body = null, string $category = 'general'): void
    {
        try {
            $userIds = User::query()->pluck('id');
            foreach ($userIds as $userId) {
                static::create([
                    'user_id' => $userId,
                    'level' => $level,
                    'category' => $category,
                    'title' => $title,
                    'body' => $body,
                ]);
            }
        } catch (\Throwable) {
            // Notifications are best-effort; never propagate.
        }
    }
}
