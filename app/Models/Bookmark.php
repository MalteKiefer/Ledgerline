<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A bookmark (plaintext-relational pivot, Phase 1). Owner-scoped, soft-deleted
 * trash; content plaintext + server-searchable.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $bookmark_folder_id
 * @property string|null $title
 * @property string $url
 * @property string|null $description
 * @property array<int, string>|null $tags
 * @property bool $favorite
 * @property bool $read_later
 * @property bool $read
 * @property int $version
 * @property Carbon|null $deleted_at
 */
class Bookmark extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = ['bookmark_folder_id', 'title', 'url', 'description', 'tags', 'favorite', 'read_later', 'read'];

    protected $casts = [
        'tags' => 'array',
        'favorite' => 'boolean',
        'read_later' => 'boolean',
        'read' => 'boolean',
        'version' => 'integer',
    ];
}
