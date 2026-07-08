<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A to-do task. Plain database row; trashing is Laravel soft-deletion. */
#[Fillable([
    'todo_list_id', 'title', 'description', 'url', 'priority',
    'marked', 'tags', 'due_at', 'reminder_channels', 'done',
    'enc_todo', 'is_encrypted',
])]
class Todo extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'marked' => 'boolean',
            'done' => 'boolean',
            'tags' => 'array',
            'reminder_channels' => 'array',
            'due_at' => 'datetime',
            'is_encrypted' => 'boolean',
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
