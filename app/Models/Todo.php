<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A todo task (plaintext-relational pivot, Phase 1). Owner-scoped, soft-deleted
 * trash. Content plaintext + server-searchable.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $todo_list_id
 * @property string $title
 * @property string|null $description
 * @property string|null $url
 * @property string $priority
 * @property bool $marked
 * @property bool $done
 * @property array<int, string>|null $tags
 * @property Carbon|null $due
 * @property string|null $recurrence
 * @property Carbon|null $reminded_at
 * @property int $version
 * @property Carbon|null $deleted_at
 */
class Todo extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    /** Recurring cadences a completed task can respawn on (null/none = one-off). */
    public const RECURRENCES = ['daily', 'weekly', 'monthly', 'yearly'];

    // `reminded_at` is server-only (set via forceFill/saveQuietly), never mass-assignable.
    protected $fillable = ['todo_list_id', 'title', 'description', 'url', 'priority', 'marked', 'done', 'tags', 'due', 'recurrence'];

    protected $casts = [
        'tags' => 'array',
        'marked' => 'boolean',
        'done' => 'boolean',
        'due' => 'datetime',
        'reminded_at' => 'datetime',
        'version' => 'integer',
    ];
}
