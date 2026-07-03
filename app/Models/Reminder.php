<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A scheduled reminder for a to-do's due date. The title and link are encrypted
 * at rest; a scheduled command fires the selected channels once due_at passes.
 */
#[Fillable(['todo_id', 'due_at', 'channels', 'title', 'url', 'fired_at'])]
class Reminder extends Model
{
    /** Channels a reminder may fire on (mirrors the notification settings). */
    public const CHANNELS = ['desktop', 'ntfy', 'webhook', 'mail'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'fired_at' => 'datetime',
            'channels' => 'array',
            'title' => 'encrypted',
            'url' => 'encrypted',
        ];
    }
}
