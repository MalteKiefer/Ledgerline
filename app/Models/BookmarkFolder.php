<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;

/**
 * A bookmark folder (plaintext-relational pivot, Phase 1). Nestable via parent_id;
 * owner-scoped.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $color
 * @property string|null $icon
 */
class BookmarkFolder extends Model
{
    use OwnsUserData;

    protected $fillable = ['parent_id', 'name', 'color', 'icon'];
}
