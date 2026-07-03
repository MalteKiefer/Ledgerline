<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A to-do task. Plain database row (not zero-knowledge). */
#[Fillable([
    'todo_list_id', 'title', 'description', 'url', 'priority',
    'marked', 'tags', 'due_at', 'reminder_channels', 'done', 'trashed_at',
])]
class Todo extends Model
{
    protected function casts(): array
    {
        return [
            'marked' => 'boolean',
            'done' => 'boolean',
            'tags' => 'array',
            'reminder_channels' => 'array',
            'due_at' => 'datetime',
            'trashed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TodoList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TodoList::class, 'todo_list_id');
    }

    /** @return HasOne<Reminder, $this> */
    public function reminder(): HasOne
    {
        return $this->hasOne(Reminder::class);
    }
}
