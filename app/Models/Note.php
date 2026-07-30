<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A single note (plaintext-relational pivot, Phase 1). Rows are private per user
 * via OwnsUserData; content is plaintext + server-searchable. Replaces the opaque
 * sealed notes store.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $title
 * @property string|null $body
 * @property array<int, string>|null $tags
 * @property bool $pinned
 * @property int $version
 * @property Carbon|null $deleted_at
 */
class Note extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = ['title', 'body', 'tags', 'pinned'];

    protected $casts = [
        'tags' => 'array',
        'pinned' => 'boolean',
        'version' => 'integer',
    ];
}
