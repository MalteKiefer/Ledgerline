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
 * @property int $version
 * @property Carbon|null $deleted_at
 */
class Todo extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = ['todo_list_id', 'title', 'description', 'url', 'priority', 'marked', 'done', 'tags', 'due'];

    protected $casts = [
        'tags' => 'array',
        'marked' => 'boolean',
        'done' => 'boolean',
        'due' => 'datetime',
        'version' => 'integer',
    ];
}
