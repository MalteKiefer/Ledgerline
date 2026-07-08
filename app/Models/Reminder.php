<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A scheduled reminder for a to-do's due date. To-dos are zero-knowledge, so the
 * reminder holds NO readable content — only the due time + channels; the
 * scheduled command fires a generic "a to-do is due" message once due_at passes.
 */
#[Fillable(['todo_id', 'due_at', 'channels', 'fired_at'])]
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
        ];
    }
}
